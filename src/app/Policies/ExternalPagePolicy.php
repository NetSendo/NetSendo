<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ExternalPage;

class ExternalPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExternalPage $externalPage): bool
    {
        return $user->id === $externalPage->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ExternalPage $externalPage): bool
    {
        return $user->id === $externalPage->user_id;
    }

    public function delete(User $user, ExternalPage $externalPage): bool
    {
        return $user->id === $externalPage->user_id;
    }
}
