<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_purchases');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->canAccessPharmacy($purchase->pharmacy)
            && $user->hasPermission('manage_purchases', $purchase->pharmacy);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_purchases');
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $this->view($user, $purchase);
    }

    public function receive(User $user, Purchase $purchase): bool
    {
        return $this->view($user, $purchase) && ! in_array($purchase->status, ['recue', 'annulee'], true);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return false;
    }
}
