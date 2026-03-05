<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'schedule_id',
        'academic_period_id',
        'session_date',
        'start_time',
        'end_time',
        'is_cancelled',
    ];

    protected $casts = [
        'session_date' => 'date',
        'is_cancelled' => 'boolean'
    ];

    public function teachingAssignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function schedule() {
        return $this->belongsTo(Schedule::class);
    }

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }

    public function isAttendanceClosed(): bool {
        return ! is_null($this->attendance_closed_at);
    }

    public function isAttendanceEditable(): bool {
        return ! $this->isAttendanceClosed() && ! $this->is_cancelled;
    }

    public function sessionActivity() {
        return $this->hasOne(SessionActivity::class);
    }
}
