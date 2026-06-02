<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityProcess extends Model
{
    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
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

