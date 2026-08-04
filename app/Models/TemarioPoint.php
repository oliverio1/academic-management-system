<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemarioPoint extends Model
{
    protected $fillable = [
        'temario_id',
        'parent_id',
        'position',
        'label',
        'sort_key',
        'level',
        'type',
        'title',
        'objective',
        'hours',
        'content',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
    ];

    public function temario()
    {
        return $this->belongsTo(Temario::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
