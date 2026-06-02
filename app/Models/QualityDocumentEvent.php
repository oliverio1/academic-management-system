<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityDocumentEvent extends Model
{
    protected $fillable = [
        'quality_document_id',
        'quality_document_version_id',
        'user_id',
        'event_type',
        'notes',
    ];

    public function document()
    {
        return $this->belongsTo(QualityDocument::class, 'quality_document_id');
    }

    public function version()
    {
        return $this->belongsTo(QualityDocumentVersion::class, 'quality_document_version_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

