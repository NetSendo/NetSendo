<?php

namespace App\Policies;

use App\Models\SubscriptionForm;
use App\Models\User;

class SubscriptionFormPolicy
{
    /**
     * Determine whether the user can view the form.
     */
    public function view(User $user, SubscriptionForm $form): bool
    {
        return $user->id === $form->user_id;
    }

    /**
     * Determine whether the user can create forms.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the form.
     */
    public function update(User $user, SubscriptionForm $form): bool
    {
        return $user->id === $form->user_id;
    }

    /**
     * Determine whether the user can delete the form.
     */
    public function delete(User $user, SubscriptionForm $form): bool
    {
        return $user->id === $form->user_id;
    }
}
