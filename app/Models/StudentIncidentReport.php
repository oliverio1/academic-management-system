<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentIncidentReport extends Model
{
    protected $fillable = [
        'student_id',
        'report_to',
        'category',
        'subject',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

