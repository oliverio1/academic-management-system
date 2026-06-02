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
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'question_sequence' => 'array',
        'options_sequence' => 'array',
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
}
