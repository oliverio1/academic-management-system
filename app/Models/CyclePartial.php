<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CyclePartial extends Model
{
    protected $fillable = [
        'school_cycle_id',
        'academic_period_id',
        'name',
        'code',
        'sort_order',
        'start_date',
        'end_date',
        'teacher_capture_deadline_at',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'teacher_capture_deadline_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function economicActas()
    {
        return $this->hasMany(EconomicActa::class, 'cycle_partial_id');
    }
}
