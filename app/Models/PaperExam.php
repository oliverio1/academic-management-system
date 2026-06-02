<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperExam extends Model
{
    protected $fillable = [
        'created_by',
        'teaching_assignment_id',
        'school_cycle_id',
        'cycle_partial_id',
        'title',
        'instructions',
        'duration_minutes',
        'is_online_enabled',
        'online_available_from',
        'online_available_until',
        'online_max_attempts',
        'online_show_result',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_online_enabled' => 'boolean',
        'online_show_result' => 'boolean',
        'online_available_from' => 'datetime',
        'online_available_until' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignment()
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function partial()
    {
        return $this->belongsTo(CyclePartial::class, 'cycle_partial_id');
    }

    public function examQuestions()
    {
        return $this->hasMany(PaperExamQuestion::class)->orderBy('sort_order');
    }

    public function attempts()
    {
        return $this->hasMany(PaperExamAttempt::class);
    }
}
