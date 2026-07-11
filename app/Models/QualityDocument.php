<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityDocument extends Model
{
    public const DOCUMENT_TYPE_LABELS = [
        'manual' => 'Manual',
        'policy' => 'Política',
        'procedure' => 'Procedimiento',
        'format' => 'Formato',
        'record' => 'Registro',
        'matrix' => 'Matriz',
        'plan' => 'Plan',
        'other' => 'Otro',
    ];

    protected $fillable = [
        'quality_process_id',
        'code',
        'title',
        'document_type',
        'version',
        'status',
        'approval_status',
        'effective_date',
        'review_date',
        'owner',
        'iso_9001_clauses',
        'iso_21001_clauses',
        'content',
        'current_version_id',
        'is_active',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'review_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function process()
    {
        return $this->belongsTo(QualityProcess::class, 'quality_process_id');
    }

    public function versions()
    {
        return $this->hasMany(QualityDocumentVersion::class)->orderByDesc('id');
    }

    public function currentVersion()
    {
        return $this->belongsTo(QualityDocumentVersion::class, 'current_version_id');
    }

    public function events()
    {
        return $this->hasMany(QualityDocumentEvent::class)->orderByDesc('id');
    }
}
