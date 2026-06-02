<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCycle extends Model
{
    protected $fillable = [
        'campus_id',
        'modality_id',
        'name',
        'code',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function campuses()
    {
        return $this->belongsToMany(Campus::class, 'school_cycle_campus')->withTimestamps();
    }

    public function modalities()
    {
        return $this->belongsToMany(Modality::class, 'school_cycle_modality')->withTimestamps();
    }

    public function partials()
    {
        return $this->hasMany(CyclePartial::class)->orderBy('sort_order');
    }

    public function cycleGroups()
    {
        return $this->hasMany(SchoolCycleGroup::class)->where('is_active', true);
    }

    public function hasModality(int $modalityId): bool
    {
        return $this->modalities->contains('id', $modalityId);
    }
}
