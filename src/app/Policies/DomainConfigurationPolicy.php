<?php

namespace App\Policies;

use App\Models\DomainConfiguration;
use App\Models\User;

class DomainConfigurationPolicy
{
    /**
     * Determine if the user can view the domain configuration.
     */
    public function view(User $user, DomainConfiguration $domain): bool
    {
        return $user->id === $domain->user_id;
    }

    /**
     * Determine if the user can update the domain configuration.
     */
    public function update(User $user, DomainConfiguration $domain): bool
    {
        return $user->id === $domain->user_id;
    }

    /**
     * Determine if the user can delete the domain configuration.
     */
    public function delete(User $user, DomainConfiguration $domain): bool
    {
        return $user->id === $domain->user_id;
    }
}
