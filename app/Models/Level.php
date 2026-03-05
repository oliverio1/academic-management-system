<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = [
        'modality_id',
        'name',
        'is_active'
    ];

    public function groups() {
        return $this->hasMany(Group::class);
    }

    public function subjects() {
        return $this->hasMany(Subject::class);
    }

    public function modality() {
        return $this->belongsTo(Modality::class);
    }
}
