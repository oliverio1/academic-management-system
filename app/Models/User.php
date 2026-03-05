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

    public function isAdmin() {
        return $this->role === 'admin';
    }

    public function isTeacher() {
        return $this->role === 'teacher';
    }

    public function isStudent() {
        return $this->role === 'student';
    }

    public function getIsActiveAttribute(): bool {
        if ($this->hasRole('student') && $this->student) {
            return (bool) $this->student->is_active;
        }

        if ($this->hasRole('teacher') && $this->teacher) {
            return (bool) $this->teacher->is_active;
        }

        if ($this->hasRole('coordination') && $this->coordinator) {
            return (bool) $this->coordinator->is_active;
        }

        if ($this->hasRole('admin')) {
            return true;
        }

        return false;
    }

    public function getRoleLabelAttribute() {
        $map = [
            'teacher'     => 'Profesor',
            'coordinator' => 'Coordinación',
            'student'     => 'Alumno',
            'tutor'       => 'Tutor',
            'admin'       => 'Administrador',
        ];

        $role = $this->getRoleNames()->first();

        return $map[$role] ?? ucfirst($role);
    }
}
