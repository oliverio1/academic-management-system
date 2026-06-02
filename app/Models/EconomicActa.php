<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicActa extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'cycle_partial_id',
        'status',
        'is_auto_closed',
        'is_late_closure_acta',
        'submitted_by',
        'submitted_at',
        'drafted_by',
        'drafted_at',
        'closed_by',
        'closed_at',
        'sent_by',
        'sent_at',
        'auto_closed_at',
        'sent_reference',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'drafted_at' => 'datetime',
        'closed_at' => 'datetime',
        'sent_at' => 'datetime',
        'auto_closed_at' => 'datetime',
        'is_auto_closed' => 'boolean',
        'is_late_closure_acta' => 'boolean',
    ];

    public function assignment()
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function partial()
    {
        return $this->belongsTo(CyclePartial::class, 'cycle_partial_id');
    }

    public function draftedByUser()
    {
        return $this->belongsTo(User::class, 'drafted_by');
    }

    public function submittedByUser()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sentByUser()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function events()
    {
        return $this->hasMany(EconomicActaEvent::class)->orderByDesc('changed_at');
    }

    public function reopenRequests()
    {
        return $this->hasMany(EconomicActaReopenRequest::class)->latest('id');
    }
}
