<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicResolution extends Model
{
    protected $fillable = [
        'student_id',
        'teaching_assignment_id',
        'academic_period_id',
        'type',
        'value',
        'reason',
        'resolved_by'
    ];
}
