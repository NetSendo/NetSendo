<?php

namespace App\Policies;

use App\Models\StripeProduct;
use App\Models\User;

class StripeProductPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StripeProduct $product): bool
    {
        return $user->id === $product->user_id || $user->admin_id === $product->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StripeProduct $product): bool
    {
        return $user->id === $product->user_id || $user->admin_id === $product->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StripeProduct $product): bool
    {
        return $user->id === $product->user_id || $user->admin_id === $product->user_id;
    }
}
