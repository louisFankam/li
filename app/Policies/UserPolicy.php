<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function view(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function update(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->canManageUser($target);
    }
}
