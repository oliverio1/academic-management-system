<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DidacticPlan extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'school_cycle_id',
        'academic_period_id',
        'temario_unit_point_id',
        'title',
        'field_training',
        'objective',
        'evaluation_instruments',
        'general_resources',
        'bibliography',
        'complementary_bibliography',
        'start_date',
        'end_date',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function assignment()
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function academicPeriod()
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function temarioUnitPoint()
    {
        return $this->belongsTo(TemarioPoint::class, 'temario_unit_point_id');
    }

    public function items()
    {
        return $this->hasMany(DidacticPlanItem::class)->orderBy('position');
    }
}
