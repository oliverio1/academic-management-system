<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroupHistory extends Model
{
    protected $fillable = [
        'student_id',
        'group_id',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function group() {
        return $this->belongsTo(Group::class);
    }
}