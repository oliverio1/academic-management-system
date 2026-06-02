<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherStudentReport extends Model
{
    protected $fillable = [
        'teacher_id',
        'group_id',
        'student_id',
        'report_type',
        'reason',
        'severity',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'severity' => 'integer',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

