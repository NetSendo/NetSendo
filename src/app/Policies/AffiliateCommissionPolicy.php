<?php

namespace App\Policies;

use App\Models\AffiliateCommission;
use App\Models\User;

class AffiliateCommissionPolicy
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
    public function view(User $user, AffiliateCommission $commission): bool
    {
        return $user->id === $commission->offer->program->user_id || $user->admin_id === $commission->offer->program->user_id;
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
    public function update(User $user, AffiliateCommission $commission): bool
    {
        return $user->id === $commission->offer->program->user_id || $user->admin_id === $commission->offer->program->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AffiliateCommission $commission): bool
    {
        return $user->id === $commission->offer->program->user_id || $user->admin_id === $commission->offer->program->user_id;
    }
}
