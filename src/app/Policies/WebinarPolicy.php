<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webinar;

class WebinarPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Webinar $webinar): bool
    {
        return $user->id === $webinar->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Webinar $webinar): bool
    {
        return $user->id === $webinar->user_id;
    }

    public function delete(User $user, Webinar $webinar): bool
    {
        return $user->id === $webinar->user_id;
    }
}
