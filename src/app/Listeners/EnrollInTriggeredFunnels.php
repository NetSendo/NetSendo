<?php

namespace App\Listeners;

use App\Events\SubscriberSignedUp;
use App\Events\TagAdded;
use App\Models\FormSubmission;
use App\Models\Funnel;
use App\Models\Subscriber;
use App\Services\Funnels\FunnelExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Enroll a subscriber in the active funnels their event triggers. Nothing else
 * reads a funnel's trigger: without this, only an automation's "Start funnel"
 * action ever put anyone into a funnel.
 *
 * - `list_signup`: SubscriberSignedUp on the trigger list, while the membership
 *   is still active when the queued listener runs
 * - `form_submit`: SubscriberSignedUp from the trigger form, or the confirmation
 *   of a double opt-in signup on its list that was made through that form
 * - `tag_added`: TagAdded with the trigger tag's name (case-insensitive)
 *
 * A subscriber is enrolled in a funnel once (FunnelSubscriber::enroll()).
 */
class EnrollInTriggeredFunnels implements ShouldQueue
{
    public function __construct(protected FunnelExecutionService $executionService)
    {
    }

    public function handle(object $event): void
    {
        [$subscriber, $funnels] = match (true) {
            $event instanceof SubscriberSignedUp => [$event->subscriber, $this->funnelsForSignup($event)],
            $event instanceof TagAdded => [$event->subscriber, $this->funnelsForTag($event)],
            default => [null, collect()],
        };

        foreach ($funnels as $funnel) {
            try {
                $this->executionService->enrollSubscriber($funnel, $subscriber);
            } catch (\Throwable $e) {
                Log::error("Enrolling subscriber {$subscriber->id} in funnel {$funnel->id} failed: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
            }
        }
    }

    protected function funnelsForSignup(SubscriberSignedUp $event): Collection
    {
        $list = $event->list;

        $isActiveMember = $event->subscriber->contactLists()
            ->wherePivot('status', Subscriber::STATUS_ACTIVE)
            ->whereKey($list->id)
            ->exists();

        if (!$isActiveMember) {
            return collect();
        }

        $funnels = Funnel::active()
            ->where('user_id', $list->user_id)
            ->where(function ($query) use ($list) {
                $query->where(fn ($q) => $q
                    ->where('trigger_type', Funnel::TRIGGER_LIST_SIGNUP)
                    ->where('trigger_list_id', $list->id))
                    ->orWhere(fn ($q) => $q
                        ->where('trigger_type', Funnel::TRIGGER_FORM_SUBMIT)
                        ->whereHas('triggerForm', fn ($form) => $form->where('contact_list_id', $list->id)));
            })
            ->get();

        return $funnels->filter(function (Funnel $funnel) use ($event) {
            if ($funnel->trigger_type === Funnel::TRIGGER_LIST_SIGNUP) {
                return true;
            }

            if ($event->form) {
                return $event->form->id === $funnel->trigger_form_id;
            }

            // A double opt-in form signs up only on confirmation, which does not
            // know the form: the submission does
            return $event->source === 'activation'
                && FormSubmission::where('subscription_form_id', $funnel->trigger_form_id)
                    ->where('subscriber_id', $event->subscriber->id)
                    ->exists();
        });
    }

    protected function funnelsForTag(TagAdded $event): Collection
    {
        $name = mb_strtolower(trim($event->tag->name));

        return Funnel::active()
            ->where('user_id', $event->subscriber->user_id)
            ->where('trigger_type', Funnel::TRIGGER_TAG_ADDED)
            ->whereNotNull('trigger_tag')
            ->get()
            ->filter(fn (Funnel $funnel) => mb_strtolower(trim($funnel->trigger_tag)) === $name);
    }
}
