<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionFillBlank extends Model
{
    protected $fillable = [
        'question_id',
        'expected_answer',
        'sort_order',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}

