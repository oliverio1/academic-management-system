<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Grade;
use App\Models\Activity;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'guardian_user_id',
        'group_id',
        'enrollment_number',
        'phone',
        'address',
        'is_active',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function group() {
        return $this->belongsTo(Group::class);
    }

    public function guardian() {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }

    public function grades() {
        return $this->hasMany(Grade::class);
    }

    public function gradeForActivity(Activity $activity): ?Grade {
        return $this->grades->where('activity_id', $activity->id)->first();
    }

    public function groupHistories() {
        return $this->hasMany(StudentGroupHistory::class);
    }

    public function teams() {
        return $this->belongsToMany(Team::class,'team_student');
    }

    public function followUps(){
        return $this->hasMany(StudentFollowUp::class);
    }

    public function attendanceJustifications() {
        return $this->hasMany(AttendanceJustification::class);
    }

    public function teacherReports() {
        return $this->hasMany(TeacherStudentReport::class);
    }

    public function incidentReports() {
        return $this->hasMany(StudentIncidentReport::class);
    }

    public function teachingAssignments()
    {
        return $this->belongsToMany(TeachingAssignment::class, 'teaching_assignment_student')
            ->withTimestamps();
    }
}
