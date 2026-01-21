<?php

namespace App\Policies;

use App\Models\AffiliatePayout;
use App\Models\User;

class AffiliatePayoutPolicy
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
    public function view(User $user, AffiliatePayout $payout): bool
    {
        return $user->id === $payout->program->user_id || $user->admin_id === $payout->program->user_id;
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
    public function update(User $user, AffiliatePayout $payout): bool
    {
        return $user->id === $payout->program->user_id || $user->admin_id === $payout->program->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AffiliatePayout $payout): bool
    {
        return $user->id === $payout->program->user_id || $user->admin_id === $payout->program->user_id;
    }
}
