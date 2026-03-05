<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StudentPolicy
{
    public function viewAny(User $user): bool {
        return $user->hasRole('admin');
    }

     public function view(User $user, Student $student): bool {
        return false;
    }

     public function create(User $user): bool {
        return $user->hasRole('admin');
    }

     public function update(User $user, Student $student): bool {
        return $user->hasRole('admin');
    }
    
    public function deactivate(User $user, Student $student): bool {
        return $user->hasRole('admin');
    }

    public function activate(User $user, Student $student): bool {
        return $user->hasRole('admin');
    }
}
