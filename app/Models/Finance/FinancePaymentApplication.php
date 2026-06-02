<?php

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePaymentApplication extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'payment_id',
        'charge_id',
        'applied_amount',
        'applied_at',
        'created_by',
    ];

    protected $casts = [
        'applied_amount' => 'decimal:2',
        'applied_at' => 'datetime',
    ];

    public function payment(): BelongsTo { return $this->belongsTo(FinancePayment::class, 'payment_id'); }
    public function charge(): BelongsTo { return $this->belongsTo(FinanceCharge::class, 'charge_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}

