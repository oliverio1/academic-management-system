<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherCampusAttendance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'campus_id',
        'attendance_date',
        'first_class_start_time',
        'check_in_time',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_time',
        'check_out_latitude',
        'check_out_longitude',
        'status',
        'minutes_late',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
