<?php

namespace App\Services\Mail;

use App\Models\ContactList;
use App\Models\Mailbox;

/**
 * Who sends a campaign when the mailbox named for it cannot send campaigns.
 *
 * A Gmail mailbox never sends broadcasts, but it is a sensible default mailbox
 * for a list: autoresponders, system emails and individual emails go out from
 * it. Such a list names a second mailbox for its campaigns — its broadcast
 * mailbox, usually an SMTP mailbox on the owner's domain — and a campaign to
 * the list goes out from there. Without one the campaign is refused: before
 * 2.12.2 it went out from the first mailbox of the account allowed to send
 * broadcasts, an address nobody chose for it.
 *
 * SendEmailJob, the API (send/schedule) and the panel (send/schedule) all ask
 * this class, so the three cannot disagree about a campaign.
 */
class CampaignSender
{
    /**
     * The mailbox named for the campaign: its own, else the default mailbox of
     * the first of its lists that has one. Null when nothing names one — the
     * account's best mailbox for broadcasts is used then, and it can send them.
     *
     * @param  iterable<ContactList>  $lists  the campaign's lists, in its order
     */
    public static function namedMailbox(?Mailbox $own, iterable $lists): ?Mailbox
    {
        if ($own) {
            return $own;
        }

        foreach ($lists as $list) {
            if ($list->default_mailbox_id) {
                $mailbox = $list->defaultMailbox;
                if ($mailbox && $mailbox->is_active) {
                    return $mailbox;
                }
            }
        }

        return null;
    }

    /**
     * The broadcast mailbox that takes over the campaign: the first of its
     * lists that names a usable one. The list goes with it, for messages.
     *
     * @param  iterable<ContactList>  $lists
     * @return array{0: ContactList, 1: Mailbox}|null
     */
    public static function listTakeover(iterable $lists): ?array
    {
        foreach ($lists as $list) {
            if ($mailbox = $list->usableBroadcastMailbox()) {
                return [$list, $mailbox];
            }
        }

        return null;
    }

    /**
     * The mailbox that keeps a campaign from being sent: the one named for it,
     * when it cannot send broadcasts and none of the campaign's lists has a
     * broadcast mailbox to take over. Null when the campaign can go out.
     *
     * @param  iterable<ContactList>  $lists
     */
    public static function blockingMailbox(?Mailbox $own, iterable $lists): ?Mailbox
    {
        $named = self::namedMailbox($own, $lists);

        if (!$named || $named->canSendType(Mailbox::TYPE_BROADCAST)) {
            return null;
        }

        return self::listTakeover($lists) ? null : $named;
    }

    /**
     * The mailbox a campaign goes out from, as the panel and the API report
     * it: the named one when it can send broadcasts, else the list's broadcast
     * mailbox. Null when nothing is named (the account's best mailbox) or when
     * the campaign is blocked.
     *
     * @param  iterable<ContactList>  $lists
     */
    public static function sender(?Mailbox $own, iterable $lists): ?Mailbox
    {
        $named = self::namedMailbox($own, $lists);

        if (!$named || $named->canSendType(Mailbox::TYPE_BROADCAST)) {
            return $named;
        }

        return self::listTakeover($lists)[1] ?? null;
    }
}
