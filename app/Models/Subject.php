<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    public const TYPE_THEORETICAL = 'Teorica';
    public const TYPE_THEORETICAL_PRACTICAL = 'Teorico-practica';

    protected $fillable = [
        'level_id',
        'name',
        'hours_per_week',
        'weekly_theory_hours',
        'weekly_practice_hours',
        'annual_hours',
        'annual_theory_hours',
        'annual_practice_hours',
        'type',
        'subject_character',
        'subject_key',
        'is_active',
    ];

    protected $casts = [
        'hours_per_week' => 'integer',
        'weekly_theory_hours' => 'integer',
        'weekly_practice_hours' => 'integer',
        'annual_hours' => 'integer',
        'annual_theory_hours' => 'integer',
        'annual_practice_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function dgireTypeOptions(): array
    {
        return [
            self::TYPE_THEORETICAL => 'Teorica',
            self::TYPE_THEORETICAL_PRACTICAL => 'Teorico-practica',
        ];
    }

    public function isTheoreticalPractical(): bool
    {
        return $this->type === self::TYPE_THEORETICAL_PRACTICAL;
    }

    public function level() {
        return $this->belongsTo(Level::class);
    }

    public function groups() {
        return $this->belongsToMany(Group::class, 'group_subject')->withTimestamps();
    }

    public function teachers() {
        return $this->belongsToMany(Teacher::class, 'subject_teacher')->withTimestamps();
    }

    public function assignments() {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function temarios()
    {
        return $this->hasMany(Temario::class);
    }

    public function cycleGroups()
    {
        return $this->belongsToMany(SchoolCycleGroup::class, 'school_cycle_group_subject')
            ->withTimestamps();
    }
}
