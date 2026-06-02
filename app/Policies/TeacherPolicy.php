<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function view(User $user, Teacher $teacher): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function update(User $user, Teacher $teacher): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function deactivate(User $user, Teacher $teacher): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function activate(User $user, Teacher $teacher): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }
}
