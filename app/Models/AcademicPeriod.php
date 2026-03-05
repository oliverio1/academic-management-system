<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    protected $fillable = [
        'name',
        'modality_id',
        'code',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function activities() {
        return $this->hasMany(Activity::class);
    }

    public function modality() {
        return $this->belongsTo(Modality::class);
    }

    public static function activeForModality(int $modalityId): ?self
    {
        return static::where('modality_id', $modalityId)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }
}