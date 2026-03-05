<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamGrade extends Model
{
    protected $fillable = [
        'activity_id',
        'team_id',
        'score',
        'comments'
    ];

    public function activity() {
        return $this->belongsTo(Activity::class);
    }

    public function team() {
        return $this->belongsTo(Team::class);
    }
}
