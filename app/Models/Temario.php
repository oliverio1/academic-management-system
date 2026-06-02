<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temario extends Model
{
    protected $fillable = [
        'subject_id',
        'title',
        'description',
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
