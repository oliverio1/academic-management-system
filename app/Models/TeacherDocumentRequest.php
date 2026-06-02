<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherDocumentRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'school_cycle_id',
        'title',
        'instructions',
        'due_date',
        'status',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(TeacherDocumentRequestItem::class, 'request_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cycle()
    {
        return $this->belongsTo(SchoolCycle::class, 'school_cycle_id');
    }
}

