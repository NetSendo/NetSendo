<?php

namespace App\Listeners;

use App\Events\CrmActivityLogged;
use App\Events\CrmContactCreated;
use App\Events\CrmContactStatusChanged;
use App\Events\CrmDealCreated;
use App\Events\CrmDealIdle;
use App\Events\CrmDealStageChanged;
use App\Events\CrmScoreThresholdReached;
use App\Events\CrmTaskCompleted;
use App\Events\CrmTaskOverdue;
use App\Services\Automation\AutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CrmAutomationListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected AutomationService $automationService
    ) {}

    /**
     * Handle CRM deal stage changed event.
     */
    public function handleDealStageChanged(CrmDealStageChanged $event): void
    {
        $context = $event->getContext();

        // Trigger general stage changed
        $this->automationService->processEvent('crm_deal_stage_changed', $context);

        // Trigger deal won if applicable
        if ($event->newStage->is_won) {
            $this->automationService->processEvent('crm_deal_won', $context);
        }

        // Trigger deal lost if applicable
        if ($event->newStage->is_lost) {
            $this->automationService->processEvent('crm_deal_lost', $context);
        }
    }

    /**
     * Handle CRM deal created event.
     */
    public function handleDealCreated(CrmDealCreated $event): void
    {
        $this->automationService->processEvent('crm_deal_created', $event->getContext());
    }

    /**
     * Handle CRM deal idle event.
     */
    public function handleDealIdle(CrmDealIdle $event): void
    {
        $this->automationService->processEvent('crm_deal_idle', $event->getContext());
    }

    /**
     * Handle CRM task completed event.
     */
    public function handleTaskCompleted(CrmTaskCompleted $event): void
    {
        $this->automationService->processEvent('crm_task_completed', $event->getContext());
    }

    /**
     * Handle CRM task overdue event.
     */
    public function handleTaskOverdue(CrmTaskOverdue $event): void
    {
        $this->automationService->processEvent('crm_task_overdue', $event->getContext());
    }

    /**
     * Handle CRM contact created event.
     */
    public function handleContactCreated(CrmContactCreated $event): void
    {
        $this->automationService->processEvent('crm_contact_created', $event->getContext());
    }

    /**
     * Handle CRM contact status changed event.
     */
    public function handleContactStatusChanged(CrmContactStatusChanged $event): void
    {
        $this->automationService->processEvent('crm_contact_status_changed', $event->getContext());
    }

    /**
     * Handle CRM score threshold reached event.
     */
    public function handleScoreThresholdReached(CrmScoreThresholdReached $event): void
    {
        $this->automationService->processEvent('crm_score_threshold', $event->getContext());
    }

    /**
     * Handle CRM activity logged event.
     */
    public function handleActivityLogged(CrmActivityLogged $event): void
    {
        $this->automationService->processEvent('crm_activity_logged', $event->getContext());
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            CrmDealStageChanged::class => 'handleDealStageChanged',
            CrmDealCreated::class => 'handleDealCreated',
            CrmDealIdle::class => 'handleDealIdle',
            CrmTaskCompleted::class => 'handleTaskCompleted',
            CrmTaskOverdue::class => 'handleTaskOverdue',
            CrmContactCreated::class => 'handleContactCreated',
            CrmContactStatusChanged::class => 'handleContactStatusChanged',
            CrmScoreThresholdReached::class => 'handleScoreThresholdReached',
            CrmActivityLogged::class => 'handleActivityLogged',
        ];
    }
}
