<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeachingAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'group_id',
        'school_cycle_group_id',
        'subject_id',
        'section_number',
        'nrc',
        'is_active',
    ];

    public function teacher() {
        return $this->belongsTo(Teacher::class);
    }

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function schoolCycleGroup()
    {
        return $this->belongsTo(SchoolCycleGroup::class);
    }

    public function subject() {
        return $this->belongsTo(Subject::class);
    }

    public function schedules() {
        return $this->hasMany(Schedule::class);
    }

    public function activities() {
        return $this->hasMany(Activity::class);
    }

    public function weights() {
        return $this->hasMany(CourseWeight::class);
    }

    public function teams() {
        return $this->hasMany(Team::class);
    }

    public function practices() {
        return $this->hasMany(Practice::class);
    }

    public function temarios() {
        return $this->hasMany(Temario::class, 'subject_id', 'subject_id');
    }

    public function didacticPlans() {
        return $this->hasMany(DidacticPlan::class);
    }

    public function economicActas()
    {
        return $this->hasMany(EconomicActa::class, 'teaching_assignment_id');
    }

    public function evaluationCriteria() {
        return $this->hasMany(EvaluationCriterion::class);
    }

    public function remedialExams()
    {
        return $this->hasMany(AssignmentRemedialExam::class, 'teaching_assignment_id');
    }

    public function hasGrades(): bool {
        return $this->activities()->whereHas('grades')->exists();
    }

    public function academicSessions() {
        return $this->hasMany(AcademicSession::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'teaching_assignment_student')
            ->withTimestamps();
    }

    public function enrolledStudents()
    {
        return $this->students()
            ->where('students.is_active', true)
            ->with('user');
    }

    public function attendancePercentageForStudent($studentId, $period)
    {
        $totalSessions = Attendance::whereHas('academicSession', function ($q) use ($period) {
                $q->where('teaching_assignment_id', $this->id)
                    ->whereBetween('session_date', [
                        $period->start_date,
                        $period->end_date
                    ]);
            })
            ->distinct(['academic_session_id', 'student_id'])
            ->count();

        if ($totalSessions === 0) {
            return 0;
        }

        // Sesiones asistidas
        $attended = Attendance::where('student_id', $studentId)
            ->whereHas('academicSession', function ($q) use ($period) {
                $q->where('teaching_assignment_id', $this->id)
                    ->whereBetween('session_date', [
                        $period->start_date,
                        $period->end_date
                    ]);
            })
            ->whereIn('status', ['present', 'late']) // decisión escolar
            ->distinct(['academic_session_id', 'student_id'])
            ->count();

        return round(($attended / $totalSessions) * 100, 2);
    }
}
