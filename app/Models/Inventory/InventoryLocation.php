<?php

namespace App\Models\Inventory;

use App\Models\Campus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'name',
        'area_type',
        'responsible',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'location_id');
    }
}
