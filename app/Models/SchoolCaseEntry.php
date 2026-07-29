<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolCaseEntry extends Model
{
    protected $fillable = [
        'school_case_id',
        'user_id',
        'entry_type',
        'visibility',
        'body',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function schoolCase()
    {
        return $this->belongsTo(SchoolCase::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
