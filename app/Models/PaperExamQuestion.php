<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExamQuestion extends Model
{
    protected $fillable = [
        'paper_exam_id',
        'question_id',
        'sort_order',
        'points_override',
    ];

    protected $casts = [
        'points_override' => 'decimal:2',
    ];

    public function exam()
    {
        return $this->belongsTo(PaperExam::class, 'paper_exam_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}

