<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function view(User $user, Student $student): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function deactivate(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function activate(User $user, Student $student): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }
}
