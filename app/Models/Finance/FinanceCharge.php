<?php

namespace App\Models\Finance;

use App\Models\Campus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\SchoolCycle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceCharge extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'school_cycle_id',
        'student_id',
        'concept_id',
        'reference',
        'description',
        'amount',
        'due_date',
        'issued_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'issued_at' => 'datetime',
    ];

    public function campus(): BelongsTo { return $this->belongsTo(Campus::class); }
    public function schoolCycle(): BelongsTo { return $this->belongsTo(SchoolCycle::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function concept(): BelongsTo { return $this->belongsTo(FinanceConcept::class, 'concept_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function applications(): HasMany { return $this->hasMany(FinancePaymentApplication::class, 'charge_id'); }

    public function appliedAmount(): float
    {
        return (float) $this->applications()->sum('applied_amount');
    }

    public function pendingAmount(): float
    {
        return max(0, (float) $this->amount - $this->appliedAmount());
    }
}

