<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicActaReopenRequest extends Model
{
    protected $fillable = [
        'economic_acta_id',
        'requested_by',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'response_comment',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function economicActa()
    {
        return $this->belongsTo(EconomicActa::class);
    }

    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

