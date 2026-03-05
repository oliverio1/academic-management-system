<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'phone',
        'address',
        'is_active',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function subjects() {
        return $this->belongsToMany(Subject::class, 'subject_teacher')->withTimestamps();
    }

    public function assignments() {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function teachingAssignments() {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function followUpAssignments() {
        return $this->hasMany(StudentFollowUpTeacher::class);
    }
}
