<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'teaching_assignment_id',
        'school_cycle_id',
        'section_number',
        'day_of_week',
        'start_time',
        'end_time',
        'type',
        'is_active',
    ];

    public function assignment() {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function attendances() {
        return $this->hasManyThrough(
            Attendance::class,
            AcademicSession::class,
            'schedule_id',
            'academic_session_id',
            'id',
            'id'
        );
    }

    public function teachingAssignment() {
        return $this->assignment();
    }
}
