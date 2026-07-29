<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExamAttempt extends Model
{
    protected $fillable = [
        'paper_exam_id',
        'student_id',
        'attempt_number',
        'started_at',
        'submitted_at',
        'status',
        'score',
        'max_score',
        'question_sequence',
        'options_sequence',
        'exam_payload',
        'autosave_payload',
        'autosaved_at',
        'locked_at',
        'lock_reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'autosaved_at' => 'datetime',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'question_sequence' => 'array',
        'options_sequence' => 'array',
        'exam_payload' => 'array',
        'autosave_payload' => 'array',
    ];

    public function exam()
    {
        return $this->belongsTo(PaperExam::class, 'paper_exam_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function answers()
    {
        return $this->hasMany(PaperExamAttemptAnswer::class, 'paper_exam_attempt_id');
    }

    public function events()
    {
        return $this->hasMany(PaperExamAttemptEvent::class, 'paper_exam_attempt_id');
    }
}
