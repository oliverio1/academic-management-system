<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeSubmission extends Model
{
    protected $fillable = [
        'practice_id',
        'team_id',
        'submitted_by',
        'theoretical_framework',
        'objectives',
        'hypothesis',
        'development',
        'results',
        'discussion',
        'conclusions',
        'references',
        'questionnaire_answers',
        'custom_field_answers',
        'score',
        'teacher_corrections',
        'teacher_comments',
        'teacher_suggestions',
        'reviewed_by',
        'reviewed_at',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'questionnaire_answers' => 'array',
        'custom_field_answers' => 'array',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function practice() {
        return $this->belongsTo(Practice::class);
    }

    public function team() {
        return $this->belongsTo(Team::class);
    }

    public function submittedBy() {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy() {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
