<?php

namespace App\Policies;

use App\Models\Pharmacy;
use App\Models\User;

class PharmacyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_pharmacies') || $user->pharmacies()->exists();
    }

    public function view(User $user, Pharmacy $pharmacy): bool
    {
        return $user->canAccessPharmacy($pharmacy);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_pharmacies');
    }

    public function update(User $user, Pharmacy $pharmacy): bool
    {
        return $user->hasPermission('manage_pharmacies');
    }

    public function delete(User $user, Pharmacy $pharmacy): bool
    {
        return $user->hasPermission('manage_pharmacies');
    }
}
