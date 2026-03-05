<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFollowUpTeacher extends Model
{
    protected $table = 'student_follow_up_teachers';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_READ      = 'read';
    public const STATUS_ANSWERED  = 'answered';

    protected $fillable = [
        'student_follow_up_id',
        'teacher_id',
        'status',
        'answered_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function followUp()
    {
        return $this->belongsTo(StudentFollowUp::class,'student_follow_up_id');
    }

    public function studentFollowUp()
    {
        return $this->belongsTo(StudentFollowUp::class, 'student_follow_up_id');
    }
    
    public function teacher() {
        return $this->belongsTo(Teacher::class);
    }

    public function response()
    {
        return $this->hasOne(
            StudentFollowUpResponse::class,
            'student_follow_up_teacher_id'
        );
    }

    public function student()
    {
        return $this->hasOneThrough(
            Student::class,
            StudentFollowUp::class,
            'id',              // FK en student_follow_ups
            'id',              // FK en students
            'student_follow_up_id', // FK local
            'student_id'       // FK en student_follow_ups
        );
    }
}
