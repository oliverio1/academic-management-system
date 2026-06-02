<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherDocumentSubmission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'item_id',
        'teacher_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'submitted_at',
        'status',
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

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

