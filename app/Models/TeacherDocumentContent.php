<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherDocumentContent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'teacher_id',
        'updated_by',
        'title',
        'content_html',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TeacherDocumentRequestItem::class, 'item_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
