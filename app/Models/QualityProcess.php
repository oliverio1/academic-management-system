<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityProcess extends Model
{
    public const PROCESS_TYPE_LABELS = [
        'strategic' => 'Estratégico',
        'core' => 'Operativo / educativo',
        'support' => 'Soporte',
        'evaluation' => 'Evaluación y mejora',
    ];

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
        'process_type',
        'iso_9001_clauses',
        'iso_21001_clauses',
        'sort_order',
        'is_active',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function documents()
    {
        return $this->hasMany(QualityDocument::class)->orderBy('title');
    }
}
