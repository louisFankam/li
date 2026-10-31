<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('make_sales') || $user->hasPermission('view_reports');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->canAccessPharmacy($sale->pharmacy)
            && ($user->hasPermission('make_sales', $sale->pharmacy) || $user->hasPermission('view_reports', $sale->pharmacy));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('make_sales');
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $sale->status !== 'cancelled'
            && $user->canAccessPharmacy($sale->pharmacy)
            && $user->hasPermission('cancel_sale', $sale->pharmacy);
    }

    public function delete(User $user, Sale $sale): bool
    {
        // Une vente n'est jamais supprimée : elle est annulée et auditée.
        return false;
    }
}
