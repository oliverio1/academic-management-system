<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SchoolCycleGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'school_cycle_id',
        'group_id',
        'campus_id',
        'modality_id',
        'section_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'section_count' => 'integer',
    ];

    public function schoolCycle()
    {
        return $this->belongsTo(SchoolCycle::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function modality()
    {
        return $this->belongsTo(Modality::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'school_cycle_group_subject')
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
