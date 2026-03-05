<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicCalendarDay extends Model
{
    protected $fillable = [
        'date',
        'type',
        'name',
        'modality_id',
        'affects_teachers',
        'affects_students',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'affects_teachers' => 'boolean',
        'affects_students' => 'boolean',
    ];

    public function modality() {
        return $this->belongsTo(Modality::class);
    }
}
