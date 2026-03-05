<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'body',
        'scope',
        'audience',
        'is_active',
        'published_at',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function recipients() {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function images() {
        return $this->hasMany(AnnouncementImage::class)->orderBy('position');
    }
}
