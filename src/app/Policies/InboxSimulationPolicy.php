<?php

namespace App\Policies;

use App\Models\InboxSimulation;
use App\Models\User;

class InboxSimulationPolicy
{
    /**
     * Determine if the user can view the simulation.
     */
    public function view(User $user, InboxSimulation $simulation): bool
    {
        return $user->id === $simulation->user_id;
    }
}
