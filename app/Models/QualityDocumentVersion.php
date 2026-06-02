<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityDocumentVersion extends Model
{
    protected $fillable = [
        'quality_document_id',
        'version',
        'content',
        'change_summary',
        'approval_status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_comment',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(QualityDocument::class, 'quality_document_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

