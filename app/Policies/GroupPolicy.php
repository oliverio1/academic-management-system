<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function view(User $user, Group $group): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function update(User $user, Group $group): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function activate(User $user, Group $group): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }

    public function deactivate(User $user, Group $group): bool
    {
        return $user->hasAnyRole(['coordinator', 'admin']);
    }
}
