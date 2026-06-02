<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    public function schoolCycles()
    {
        return $this->hasMany(SchoolCycle::class);
    }

    public function schoolCyclesMany()
    {
        return $this->belongsToMany(SchoolCycle::class, 'school_cycle_campus')->withTimestamps();
    }
}
