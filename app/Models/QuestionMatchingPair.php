<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionMatchingPair extends Model
{
    protected $fillable = [
        'question_id',
        'left_text',
        'right_text',
        'sort_order',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}

