<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordinationReport extends Model
{
    protected $fillable = [
        'campus_id',
        'school_cycle_id',
        'reported_by',
        'received_via',
        'reporter_name',
        'reporter_contact',
        'category',
        'subject',
        'description',
        'priority',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'priority' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
