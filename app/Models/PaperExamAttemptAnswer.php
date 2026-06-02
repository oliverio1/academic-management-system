<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExamAttemptAnswer extends Model
{
    protected $fillable = [
        'paper_exam_attempt_id',
        'question_id',
        'answer_payload',
        'is_correct',
        'score',
        'max_score',
    ];

    protected $casts = [
        'answer_payload' => 'array',
        'is_correct' => 'boolean',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
    ];

    public function attempt()
    {
        return $this->belongsTo(PaperExamAttempt::class, 'paper_exam_attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}

