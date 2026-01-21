<?php

namespace App\Policies;

use App\Models\Affiliate;
use App\Models\User;

class AffiliatePolicy
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
    public function view(User $user, Affiliate $affiliate): bool
    {
        // User can view if they own the program
        return $user->id === $affiliate->program->user_id || $user->admin_id === $affiliate->program->user_id;
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
    public function update(User $user, Affiliate $affiliate): bool
    {
        // User can update (approve/block) if they own the program
        return $user->id === $affiliate->program->user_id || $user->admin_id === $affiliate->program->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Affiliate $affiliate): bool
    {
        return $user->id === $affiliate->program->user_id || $user->admin_id === $affiliate->program->user_id;
    }
}
