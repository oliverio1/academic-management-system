<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemarioPoint extends Model
{
    protected $fillable = [
        'temario_id',
        'position',
        'label',
        'level',
        'type',
        'hours',
        'content',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
    ];

    public function temario()
    {
        return $this->belongsTo(Temario::class);
    }
}
