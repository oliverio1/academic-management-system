<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'academic_session_id',
        'student_id',
        'student_suspension_id',
        'status',
        'is_suspension_locked',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_suspension_locked' => 'boolean',
    ];

    public function academicSession() {
        return $this->belongsTo(AcademicSession::class);
    }

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function suspension()
    {
        return $this->belongsTo(StudentSuspension::class, 'student_suspension_id');
    }
}
