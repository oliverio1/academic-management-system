<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrefectIncidentReport extends Model
{
    protected $fillable = [
        'reported_by',
        'report_to',
        'category',
        'subject',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

