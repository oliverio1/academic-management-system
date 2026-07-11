<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupSubgroup extends Model
{
    public const CODE_BASIC = 'BASIC';
    public const CODE_ADVANCED = 'ADVANCED';
    public const CODE_SECTION_A = 'SECTION_A';
    public const CODE_SECTION_B = 'SECTION_B';

    protected $fillable = [
        'group_division_id',
        'name',
        'code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(GroupDivision::class, 'group_division_id');
    }

    public function classOfferingAssignments(): HasMany
    {
        return $this->hasMany(ClassOfferingSubgroup::class, 'group_subgroup_id');
    }

    public function classOfferings(): BelongsToMany
    {
        return $this->belongsToMany(
            ClassOffering::class,
            'class_offering_subgroups'
        )
            ->withPivot(['weekly_periods', 'is_active'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
