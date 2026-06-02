<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentRemedialExam extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'student_id',
        'academic_period_id',
        'ordinario_a_score',
        'ordinario_b_score',
        'extraordinario_score',
    ];

    public function assignment()
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }
}

