<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    protected $fillable = [
        'teacher_id',
        'subject_id',
        'school_cycle_id',
        'cycle_partial_id',
        'name',
        'description',
        'is_active',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function partial()
    {
        return $this->belongsTo(CyclePartial::class, 'cycle_partial_id');
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }
}

