<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Practice extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'number',
        'title',
        'instructions',
        'questionnaire',
        'due_date',
    ];

    protected $casts = [
        'questionnaire' => 'array',
        'due_date' => 'date',
    ];

    public function activity() {
        return $this->belongsTo(Activity::class);
    }

    public function teachingAssignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function submissions() {
        return $this->hasMany(PracticeSubmission::class);
    }
}
