<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFollowUpResponse extends Model
{
    protected $fillable = [
        'student_follow_up_teacher_id',
        'questionnaire',
        'comments',
    ];

    protected $casts = [
        'questionnaire' => 'array',
    ];

    public function assignment() {
        return $this->belongsTo(StudentFollowUpTeacher::class);
    }
}
