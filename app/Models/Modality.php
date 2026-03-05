<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modality extends Model
{
    protected $fillable = [
        'name',
        'is_active'
    ];

    public function levels() {
        return $this->hasMany(Level::class);
    }

    public function periods() {
        return $this->hasMany(AcademicPeriod::class);
    }
}
