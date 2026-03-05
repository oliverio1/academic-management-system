<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = [
        'activity_id',
        'student_id',
        'score',
        'comments',
    ];

    public function activity() {
        return $this->belongsTo(Activity::class);
    }

    public function student() {
        return $this->belongsTo(Student::class);
    }
}