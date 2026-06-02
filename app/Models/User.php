<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'default_campus_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function student() {
        return $this->hasOne(Student::class);
    }

    public function teacher() {
        return $this->hasOne(Teacher::class);
    }

    public function guardedStudents() {
        return $this->hasMany(Student::class, 'guardian_user_id');
    }

    public function isAdmin() {
        return $this->hasRole('admin');
    }

    public function isCoordinator() {
        return $this->hasRole('coordinator');
    }

    public function isTeacher() {
        return $this->hasRole('teacher');
    }

    public function isPrefect() {
        return $this->hasRole('prefect');
    }

    public function isStudent() {
        return $this->hasRole('student');
    }

    public function getIsActiveAttribute(): bool {
        if ($this->hasRole('student') && $this->student) {
            return (bool) $this->student->is_active;
        }

        if ($this->hasRole('teacher') && $this->teacher) {
            return (bool) $this->teacher->is_active;
        }

        if ($this->hasRole('coordinator')) return true;
        if ($this->hasRole('guardian') || $this->hasRole('tutor')) return true;

        if ($this->hasRole('admin')) {
            return true;
        }

        return false;
    }

    public function getRoleLabelAttribute() {
        $map = [
            'teacher'     => 'Profesor',
            'prefect'     => 'Prefecto',
            'coordinator' => 'Coordinacion',
            'student'     => 'Alumno',
            'guardian'    => 'Tutor',
            'tutor'       => 'Tutor',
            'admin'       => 'Administrador',
        ];

        $role = $this->getRoleNames()->first();

        return $map[$role] ?? ucfirst($role);
    }

    public function campuses()
    {
        return $this->belongsToMany(Campus::class)->withTimestamps();
    }

    public function defaultCampus()
    {
        return $this->belongsTo(Campus::class, 'default_campus_id');
    }

    public function chatParticipations()
    {
        return $this->belongsToMany(ChatConversation::class, 'chat_participants', 'user_id', 'conversation_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }
}
