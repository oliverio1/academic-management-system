<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassOfferingSubgroup extends Model
{
    protected $fillable = [
        'class_offering_id',
        'group_subgroup_id',
        'weekly_periods',
        'is_active',
    ];

    protected $casts = [
        'weekly_periods' => 'integer',
        'is_active' => 'boolean',
    ];

    public function classOffering(): BelongsTo
    {
        return $this->belongsTo(ClassOffering::class);
    }

    public function subgroup(): BelongsTo
    {
        return $this->belongsTo(GroupSubgroup::class, 'group_subgroup_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
