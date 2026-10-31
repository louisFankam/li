<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_stock') || $user->hasPermission('manage_products');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_stock') || $user->hasPermission('manage_products');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('manage_products');
    }
}
