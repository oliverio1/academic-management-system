<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Grade;
use App\Models\Activity;

class Student extends Model
{
    protected $fillable = [
        'user_id',
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
}
