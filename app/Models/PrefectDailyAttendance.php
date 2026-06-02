<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrefectDailyAttendance extends Model
{
    protected $fillable = [
        'group_id',
        'student_id',
        'attendance_date',
        'status',
        'recorded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

