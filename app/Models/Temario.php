<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temario extends Model
{
    protected $fillable = [
        'subject_id',
        'program_key',
        'area',
        'area_label',
        'title',
        'description',
        'general_objective',
        'weekly_hours',
        'annual_hours',
        'source_filename',
    ];

    protected $casts = [
        'weekly_hours' => 'integer',
        'annual_hours' => 'integer',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function points()
    {
        return $this->hasMany(TemarioPoint::class)->orderBy('position');
    }
}
