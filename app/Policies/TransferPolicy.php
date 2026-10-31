<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_transfers');
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return ($user->canAccessPharmacy($transfer->fromPharmacy) || $user->canAccessPharmacy($transfer->toPharmacy))
            && ($user->hasPermission('manage_transfers', $transfer->fromPharmacy)
                || $user->hasPermission('manage_transfers', $transfer->toPharmacy));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_transfers');
    }

    public function ship(User $user, Transfer $transfer): bool
    {
        return in_array($transfer->status, ['valide', 'partiellement_expedie'], true)
            && $user->canAccessPharmacy($transfer->fromPharmacy)
            && $user->hasPermission('manage_transfers', $transfer->fromPharmacy);
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        return in_array($transfer->status, ['expedie', 'partiellement_expedie', 'partiellement_recu'], true)
            && $user->canAccessPharmacy($transfer->toPharmacy)
            && $user->hasPermission('manage_transfers', $transfer->toPharmacy);
    }

    public function cancel(User $user, Transfer $transfer): bool
    {
        return ! in_array($transfer->status, ['recu', 'annule'], true)
            && ($user->canAccessPharmacy($transfer->fromPharmacy) || $user->canAccessPharmacy($transfer->toPharmacy))
            && ($user->hasPermission('manage_transfers', $transfer->fromPharmacy)
                || $user->hasPermission('manage_transfers', $transfer->toPharmacy));
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return false;
    }
}
