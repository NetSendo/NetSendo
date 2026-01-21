<?php

namespace App\Policies;

use App\Models\AffiliateProgram;
use App\Models\User;

class AffiliateProgramPolicy
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
    public function view(User $user, AffiliateProgram $program): bool
    {
        return $user->id === $program->user_id || $user->admin_id === $program->user_id;
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
    public function update(User $user, AffiliateProgram $program): bool
    {
        return $user->id === $program->user_id || $user->admin_id === $program->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AffiliateProgram $program): bool
    {
        return $user->id === $program->user_id || $user->admin_id === $program->user_id;
    }
}
