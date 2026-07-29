<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCaseAction extends Model
{
    protected $fillable = [
        'school_case_id',
        'title',
        'assigned_to',
        'due_at',
        'status',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function schoolCase()
    {
        return $this->belongsTo(SchoolCase::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
