<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeSubmissionAttachment extends Model
{
    protected $fillable = [
        'practice_submission_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function submission()
    {
        return $this->belongsTo(PracticeSubmission::class, 'practice_submission_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
