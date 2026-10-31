<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;

class CashSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_cash');
    }

    public function view(User $user, CashSession $session): bool
    {
        return $user->canAccessPharmacy($session->pharmacy)
            && $user->hasPermission('manage_cash', $session->pharmacy);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_cash');
    }

    public function close(User $user, CashSession $session): bool
    {
        return $this->view($user, $session) && $session->status === 'open';
    }

    public function movement(User $user, CashSession $session): bool
    {
        return $this->view($user, $session) && $session->status === 'open';
    }

    public function delete(User $user, CashSession $session): bool
    {
        return false;
    }
}
