<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceJustification extends Model
{
    protected $fillable = [
        'student_id',
        'from_date',
        'to_date',
        'reason',
        'document_path',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
        'issued_at' => 'datetime',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function issuer() {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
