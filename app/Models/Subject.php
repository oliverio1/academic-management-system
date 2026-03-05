<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'level_id',
        'name',
        'hours_per_week',
        'type',
        'is_active',
    ];

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
}
