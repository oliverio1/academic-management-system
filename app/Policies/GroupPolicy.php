<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupPolicy
{
    public function viewAny(User $user): bool {
        return $user->hasRole('admin');
    }

    public function view(User $user, Group $group): bool {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool {
        return $user->hasRole('admin');
    }

    public function update(User $user, Group $group): bool {
        return $user->hasRole('admin');
    }

    public function activate(User $user, Group $group): bool {
        return $user->hasRole('admin');
    }

    public function deactivate(User $user, Group $group): bool {
        return $user->hasRole('admin');
    }
}
