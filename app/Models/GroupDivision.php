<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupDivision extends Model
{
    public const TYPE_LEVEL = 'level';
    public const TYPE_ALPHABETICAL = 'alphabetical';

    protected $fillable = [
        'group_id',
        'name',
        'division_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function subgroups(): HasMany
    {
        return $this->hasMany(GroupSubgroup::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isLevelBased(): bool
    {
        return $this->division_type === self::TYPE_LEVEL;
    }

    public function isAlphabetical(): bool
    {
        return $this->division_type === self::TYPE_ALPHABETICAL;
    }
}
