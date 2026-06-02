<?php

namespace App\Models\Finance;

use App\Models\Campus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceConcept extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'code',
        'name',
        'description',
        'default_amount',
        'is_active',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(FinanceCharge::class, 'concept_id');
    }
}

