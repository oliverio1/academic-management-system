<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseWeight extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'activity_type',
        'weight',
    ];

    public function assignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }
}
