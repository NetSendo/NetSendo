<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\User;

class MailboxPolicy
{
    /**
     * Determine whether the user can view the mailbox.
     */
    public function view(User $user, Mailbox $mailbox): bool
    {
        return $user->id === $mailbox->user_id;
    }

    /**
     * Determine whether the user can update the mailbox.
     */
    public function update(User $user, Mailbox $mailbox): bool
    {
        return $user->id === $mailbox->user_id;
    }

    /**
     * Determine whether the user can delete the mailbox.
     */
    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $user->id === $mailbox->user_id;
    }
}
