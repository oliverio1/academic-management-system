<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCriterion extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'name',
        'percentage',
    ];

    public function TeachingAssignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function assignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function activities() {
        return $this->hasMany(Activity::class, 'evaluation_criterion_id');
    }

    public function isAttendance(): bool {
        return mb_strtolower(trim($this->name)) === 'asistencia';
    }
}
