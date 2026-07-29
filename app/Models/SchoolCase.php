<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCase extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_WAITING_RESPONSE = 'waiting_response';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'case_number',
        'campus_id',
        'school_cycle_id',
        'source_type',
        'source_user_id',
        'target_type',
        'student_id',
        'group_id',
        'teacher_id',
        'guardian_user_id',
        'location',
        'category',
        'priority',
        'status',
        'subject',
        'description',
        'assigned_to',
        'due_at',
        'public_response',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function sourceUser()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function guardian()
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function entries()
    {
        return $this->hasMany(SchoolCaseEntry::class)->latest();
    }

    public function actions()
    {
        return $this->hasMany(SchoolCaseAction::class);
    }
}
