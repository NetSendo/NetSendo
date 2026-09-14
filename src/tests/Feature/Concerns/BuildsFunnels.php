<?php

namespace Tests\Feature\Concerns;

use App\Models\ContactList;
use App\Models\Funnel;
use App\Models\FunnelStep;
use App\Models\FunnelSubscriber;
use App\Models\Message;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Funnels\FunnelExecutionService;

/**
 * Funnels, steps and subscribers created directly: there are no factories for
 * them, and the ones the older unit tests call do not exist.
 */
trait BuildsFunnels
{
    protected User $user;

    protected function setUpFunnelOwner(array $attributes = []): void
    {
        $this->user = User::factory()->create($attributes);
    }

    protected function makeList(string $name = 'Newsletter'): ContactList
    {
        return ContactList::create([
            'user_id' => $this->user->id,
            'name' => $name,
            'type' => 'email',
            'is_public' => true,
        ]);
    }

    protected function makeFunnel(array $attributes = []): Funnel
    {
        return Funnel::create($attributes + [
            'user_id' => $this->user->id,
            'name' => 'Funnel',
            'status' => Funnel::STATUS_ACTIVE,
            'trigger_type' => Funnel::TRIGGER_MANUAL,
        ]);
    }

    protected function makeStep(Funnel $funnel, string $type, array $attributes = []): FunnelStep
    {
        return FunnelStep::create($attributes + [
            'funnel_id' => $funnel->id,
            'type' => $type,
        ]);
    }

    /**
     * start → the given steps, each connected to the next on `next_step_id`.
     */
    protected function makeChain(Funnel $funnel, FunnelStep ...$steps): FunnelStep
    {
        $start = $this->makeStep($funnel, FunnelStep::TYPE_START);
        $previous = $start;

        foreach ($steps as $step) {
            $previous->update(['next_step_id' => $step->id]);
            $previous = $step;
        }

        return $start;
    }

    protected function makeSubscriber(string $email = 'jan@example.com', array $attributes = []): Subscriber
    {
        return Subscriber::create($attributes + [
            'user_id' => $this->user->id,
            'email' => $email,
            'status' => 'active',
            'is_active_global' => true,
        ]);
    }

    protected function makeEmail(string $subject = 'Hello'): Message
    {
        return Message::create([
            'user_id' => $this->user->id,
            'channel' => 'email',
            'type' => 'broadcast',
            'subject' => $subject,
            'content' => '<p>Body</p>',
            'status' => 'draft',
        ]);
    }

    protected function enroll(Funnel $funnel, Subscriber $subscriber): FunnelSubscriber
    {
        return app(FunnelExecutionService::class)->enrollSubscriber($funnel, $subscriber)->fresh();
    }

    protected function historyActions(FunnelSubscriber $enrollment): array
    {
        return array_column($enrollment->fresh()->getHistory(), 'action');
    }

    protected function historyEntry(FunnelSubscriber $enrollment, string $action): ?array
    {
        return collect($enrollment->fresh()->getHistory())->firstWhere('action', $action);
    }
}
