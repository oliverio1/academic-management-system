<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExamAttemptEvent extends Model
{
    protected $fillable = [
        'paper_exam_attempt_id',
        'event_type',
        'occurred_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function attempt()
    {
        return $this->belongsTo(PaperExamAttempt::class, 'paper_exam_attempt_id');
    }
}
