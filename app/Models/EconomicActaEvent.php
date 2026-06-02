<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicActaEvent extends Model
{
    protected $fillable = [
        'economic_acta_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'comment',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function acta()
    {
        return $this->belongsTo(EconomicActa::class, 'economic_acta_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

