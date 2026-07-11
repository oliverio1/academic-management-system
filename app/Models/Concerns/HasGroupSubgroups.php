<?php

namespace App\Models\Concerns;

use App\Models\ClassOfferingSubgroup;
use App\Models\GroupSubgroup;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasGroupSubgroups
{
    public function subgroupAssignments(): HasMany
    {
        return $this->hasMany(ClassOfferingSubgroup::class);
    }

    public function groupSubgroups(): BelongsToMany
    {
        return $this->belongsToMany(
            GroupSubgroup::class,
            'class_offering_subgroups'
        )
            ->withPivot(['weekly_periods', 'is_active'])
            ->withTimestamps();
    }

    public function activeGroupSubgroups(): BelongsToMany
    {
        return $this->groupSubgroups()
            ->wherePivot('is_active', true);
    }

    public function appliesToWholeGroup(): bool
    {
        return ! $this->groupSubgroups()->exists();
    }
}
