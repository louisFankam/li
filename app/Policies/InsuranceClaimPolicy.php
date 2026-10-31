<?php

namespace App\Policies;

use App\Models\InsuranceClaim;
use App\Models\User;

class InsuranceClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_insurance');
    }

    public function view(User $user, InsuranceClaim $claim): bool
    {
        $pharmacy = $claim->sale->pharmacy;

        return $user->canAccessPharmacy($pharmacy)
            && $user->hasPermission('manage_insurance', $pharmacy);
    }

    public function update(User $user, InsuranceClaim $claim): bool
    {
        return $this->view($user, $claim) && $claim->status !== 'annule';
    }

    public function delete(User $user, InsuranceClaim $claim): bool
    {
        return false;
    }
}
