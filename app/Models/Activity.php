<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'evaluation_criterion_id',
        'academic_period_id',
        'title',
        'max_score',
        'due_date',
        'description',
        'evaluation_mode',
        'is_active',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function assignment() {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function TeachingAssignment() {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function grades() {
        return $this->hasMany(Grade::class);
    }

    public function academicPeriod() {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function evaluationCriterion() {
        return $this->belongsTo(EvaluationCriterion::class);
    }

    public function teamGrades() {
        return $this->hasMany(TeamGrade::class);
    }
}