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
        'is_resubmission_allowed',
        'resubmission_due_date',
        'resubmission_note',
        'resubmission_requested_at',
        'resubmission_requested_by',
        'resubmission_count',
        'submitted_at',
    ];

    protected $casts = [
        'questionnaire_answers' => 'array',
        'custom_field_answers' => 'array',
        'reviewed_at' => 'datetime',
        'is_resubmission_allowed' => 'boolean',
        'resubmission_due_date' => 'date',
        'resubmission_requested_at' => 'datetime',
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

    public function resubmissionRequestedBy() {
        return $this->belongsTo(User::class, 'resubmission_requested_by');
    }

    public function attachments() {
        return $this->hasMany(PracticeSubmissionAttachment::class);
    }
}
