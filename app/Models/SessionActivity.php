<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionActivity extends Model
{
    protected $fillable = [
        'academic_session_id',
        'title',
        'description',
    ];

    public function academicSession() {
        return $this->belongsTo(AcademicSession::class);
    }

    public function evaluableActivity() {
        return $this->hasOne(Activity::class);
    }
}
