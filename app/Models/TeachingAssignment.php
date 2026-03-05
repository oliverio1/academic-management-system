<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeachingAssignment extends Model
{
    protected $fillable = [
        'teacher_id',
        'group_id',
        'subject_id',
        'is_active',
    ];

    public function teacher() {
        return $this->belongsTo(Teacher::class);
    }

    public function group() {
        return $this->belongsTo(Group::class);
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

    public function evaluationCriteria() {
        return $this->hasMany(EvaluationCriterion::class);
    }

    public function hasGrades(): bool {
        return $this->activities()->whereHas('grades')->exists();
    }

    public function academicSessions() {
        return $this->hasMany(AcademicSession::class);
    }

    public function attendancePercentageForStudent($studentId, $period)
    {
        // Total de sesiones del periodo
        $totalSessions = Attendance::whereHas('schedule', function ($q) {
                $q->where('teaching_assignment_id', $this->id);
            })
            ->whereBetween('class_date', [
                $period->start_date,
                $period->end_date
            ])
            ->distinct(['class_date', 'schedule_id'])
            ->count();

        if ($totalSessions === 0) {
            return 0;
        }

        // Sesiones asistidas
        $attended = Attendance::where('student_id', $studentId)
            ->whereHas('schedule', function ($q) {
                $q->where('teaching_assignment_id', $this->id);
            })
            ->whereBetween('class_date', [
                $period->start_date,
                $period->end_date
            ])
            ->whereIn('status', ['present', 'late']) // decisión escolar
            ->distinct(['class_date', 'schedule_id'])
            ->count();

        return round(($attended / $totalSessions) * 100, 2);
    }
}