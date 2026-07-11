<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'level_id',
        'name',
        'capacity',
        'is_active',
    ];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'group_subject')->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(GroupDivision::class);
    }

    public function modality()
    {
        return $this->hasOneThrough(
            Modality::class,
            Level::class,
            'id',          // FK en levels
            'id',          // FK en modalities
            'level_id',    // FK en groups
            'modality_id'  // FK en levels
        );
    }
}
