<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DidacticPlan extends Model
{
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_FINAL = 'final';

    protected $fillable = [
        'teaching_assignment_id',
        'school_cycle_id',
        'academic_period_id',
        'temario_unit_point_id',
        'title',
        'status',
        'generated_by_system',
        'generated_at',
        'unam_incorporation_key',
        'teacher_dgire_file',
        'technical_review_date',
        'subject_character',
        'subject_key',
        'total_annual_hours',
        'field_training',
        'objective',
        'evaluation_instruments',
        'general_resources',
        'bibliography',
        'complementary_bibliography',
        'start_date',
        'end_date',
        'notes',
        'dgire_metadata',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'technical_review_date' => 'date',
        'generated_at' => 'datetime',
        'dgire_metadata' => 'array',
        'generated_by_system' => 'boolean',
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
