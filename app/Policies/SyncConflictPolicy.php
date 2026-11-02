<?php

namespace App\Policies;

use App\Models\SyncConflict;
use App\Models\User;

class SyncConflictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_audit');
    }

    public function view(User $user, SyncConflict $conflict): bool
    {
        if ($conflict->pharmacy === null) {
            return $user->is_super_admin && $user->hasPermission('manage_audit');
        }

        return $user->canAccessPharmacy($conflict->pharmacy)
            && $user->hasPermission('manage_audit', $conflict->pharmacy);
    }

    public function resolve(User $user, SyncConflict $conflict): bool
    {
        return $this->view($user, $conflict) && $conflict->status === 'open';
    }
}
