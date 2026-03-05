<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'teaching_assignment_id',
        'name',
        'notes'
    ];
    
    public function assignment() {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function students() {
        return $this->belongsToMany(Student::class, 'team_student');
    }
}
