<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'academic_session_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'class_date' => 'date',
    ];

    public function academicSession() {
        return $this->belongsTo(AcademicSession::class);
    }

    public function student() {
        return $this->belongsTo(Student::class);
    }
}