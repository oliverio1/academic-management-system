<?php

namespace App\Models\Finance;

use App\Models\Campus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'student_id',
        'payment_date',
        'amount',
        'method',
        'reference',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function campus(): BelongsTo { return $this->belongsTo(Campus::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function applications(): HasMany { return $this->hasMany(FinancePaymentApplication::class, 'payment_id'); }

    public function appliedAmount(): float
    {
        return (float) $this->applications()->sum('applied_amount');
    }
}

