<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'question_bank_id',
        'type',
        'prompt',
        'points',
        'sort_order',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'points' => 'decimal:2',
    ];

    public function bank()
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function options()
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function matchingPairs()
    {
        return $this->hasMany(QuestionMatchingPair::class)->orderBy('sort_order');
    }

    public function fillBlanks()
    {
        return $this->hasMany(QuestionFillBlank::class)->orderBy('sort_order');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'multiple_choice' => 'Opción múltiple',
            'matching' => 'Relación de columnas',
            'fill_blank' => 'Completado de oraciones',
            'open' => 'Abierta',
            default => (string) $this->type,
        };
    }
}
