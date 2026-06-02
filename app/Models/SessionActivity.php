<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionActivity extends Model
{
    protected $fillable = [
        'academic_session_id',
        'title',
        'description',
        'evaluation_criterion_id',
        'temario_point_id',
        'temario_subtopic_ids',
    ];

    protected $casts = [
        'temario_subtopic_ids' => 'array',
    ];

    public function academicSession() {
        return $this->belongsTo(AcademicSession::class);
    }

    public function evaluableActivity() {
        return $this->hasOne(Activity::class);
    }

    public function evaluationCriterion() {
        return $this->belongsTo(EvaluationCriterion::class);
    }

    public function temarioPoint() {
        return $this->belongsTo(TemarioPoint::class);
    }
}
