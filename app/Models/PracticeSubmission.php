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
        'conclusions',
        'references',
        'questionnaire_answers',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'questionnaire_answers' => 'array',
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
}
