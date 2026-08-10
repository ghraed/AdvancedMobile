<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->canAccessAdmin();
    }

    public function create(User $user): bool
    {
        return $user->canAccessAdmin();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->canAccessAdmin();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->canAccessAdmin();
    }
}
