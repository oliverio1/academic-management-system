<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFollowUp extends Model
{
    protected $fillable = [
        'student_id',
        'requested_by',
        'type',
        'message',
        'status',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function requester() {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function teachers() {
        return $this->hasMany(StudentFollowUpTeacher::class,'student_follow_up_id');
    }

    public function teacherResponses() {
        return $this->hasManyThrough(
            StudentFollowUpResponse::class,
            StudentFollowUpTeacher::class,
            'student_follow_up_id',          // FK en student_follow_up_teachers
            'student_follow_up_teacher_id',  // FK en student_follow_up_responses
            'id',                            // PK en student_follow_ups
            'id'                             // PK en student_follow_up_teachers
        );
    }

    public function isCompleted(): bool {
        return $this->teachers()
            ->where('status', '!=', StudentFollowUpTeacher::STATUS_ANSWERED)
            ->doesntExist();
    }

    public function checkAndCloseIfCompleted(): void
    {
        $pending = $this->teachers()
            ->where('status', '!=', StudentFollowUpTeacher::STATUS_ANSWERED)
            ->exists();

        if (! $pending) {
            $this->update(['status' => 'closed']);
        }
    }
}
