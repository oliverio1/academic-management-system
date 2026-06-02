<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherDocumentRequestItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'request_id',
        'teaching_assignment_id',
        'document_type',
        'notes',
        'is_required',
        'is_student_visible',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_student_visible' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(TeacherDocumentRequest::class, 'request_id');
    }

    public function assignment()
    {
        return $this->belongsTo(TeachingAssignment::class, 'teaching_assignment_id');
    }

    public function submissions()
    {
        return $this->hasMany(TeacherDocumentSubmission::class, 'item_id');
    }

    public function latestSubmission()
    {
        return $this->hasOne(TeacherDocumentSubmission::class, 'item_id')->latestOfMany('submitted_at');
    }
}
