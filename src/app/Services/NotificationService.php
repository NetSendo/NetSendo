<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Create a notification for a user.
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $actionUrl = null,
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    /**
     * Create an info notification.
     */
    public function info(int $userId, string $title, ?string $message = null, ?string $actionUrl = null, array $data = []): Notification
    {
        return $this->create($userId, Notification::TYPE_INFO, $title, $message, $actionUrl, $data);
    }

    /**
     * Create a success notification.
     */
    public function success(int $userId, string $title, ?string $message = null, ?string $actionUrl = null, array $data = []): Notification
    {
        return $this->create($userId, Notification::TYPE_SUCCESS, $title, $message, $actionUrl, $data);
    }

    /**
     * Create a warning notification.
     */
    public function warning(int $userId, string $title, ?string $message = null, ?string $actionUrl = null, array $data = []): Notification
    {
        return $this->create($userId, Notification::TYPE_WARNING, $title, $message, $actionUrl, $data);
    }

    /**
     * Create an error notification.
     */
    public function error(int $userId, string $title, ?string $message = null, ?string $actionUrl = null, array $data = []): Notification
    {
        return $this->create($userId, Notification::TYPE_ERROR, $title, $message, $actionUrl, $data);
    }

    /**
     * Notify about new subscriber.
     */
    public function notifyNewSubscriber(User $user, string $listName, string $subscriberEmail, ?string $actionUrl = null): Notification
    {
        return $this->success(
            $user->id,
            __('notifications.new_subscriber'),
            __('notifications.new_subscriber_message', ['email' => $subscriberEmail, 'list' => $listName]),
            $actionUrl,
            ['list' => $listName, 'email' => $subscriberEmail]
        );
    }

    /**
     * Notify about campaign sent.
     */
    public function notifyCampaignSent(User $user, string $campaignName, int $recipientCount, ?string $actionUrl = null): Notification
    {
        return $this->success(
            $user->id,
            __('notifications.campaign_sent'),
            __('notifications.campaign_sent_message', ['name' => $campaignName, 'count' => $recipientCount]),
            $actionUrl,
            ['campaign' => $campaignName, 'recipients' => $recipientCount]
        );
    }

    /**
     * Notify about campaign scheduled.
     */
    public function notifyCampaignScheduled(User $user, string $campaignName, string $scheduledAt, ?string $actionUrl = null): Notification
    {
        return $this->info(
            $user->id,
            __('notifications.campaign_scheduled'),
            __('notifications.campaign_scheduled_message', ['name' => $campaignName, 'date' => $scheduledAt]),
            $actionUrl,
            ['campaign' => $campaignName, 'scheduled_at' => $scheduledAt]
        );
    }

    /**
     * Notify about automation execution.
     */
    public function notifyAutomationExecuted(User $user, string $automationName, ?string $actionUrl = null): Notification
    {
        return $this->info(
            $user->id,
            __('notifications.automation_executed'),
            __('notifications.automation_executed_message', ['name' => $automationName]),
            $actionUrl,
            ['automation' => $automationName]
        );
    }

    /**
     * Notify about SMTP error.
     */
    public function notifySmtpError(User $user, string $errorMessage, ?string $actionUrl = null): Notification
    {
        return $this->error(
            $user->id,
            __('notifications.smtp_error'),
            $errorMessage,
            $actionUrl
        );
    }

    /**
     * Notify about license expiring.
     */
    public function notifyLicenseExpiring(User $user, int $daysLeft): Notification
    {
        return $this->warning(
            $user->id,
            __('notifications.license_expiring'),
            __('notifications.license_expiring_message', ['days' => $daysLeft]),
            route('license.index')
        );
    }

    /**
     * Notify about form submission.
     */
    public function notifyFormSubmission(User $user, string $formName, ?string $actionUrl = null): Notification
    {
        return $this->info(
            $user->id,
            __('notifications.form_submission'),
            __('notifications.form_submission_message', ['form' => $formName]),
            $actionUrl,
            ['form' => $formName]
        );
    }

    /**
     * Get unread count for user.
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)->unread()->count();
    }

    /**
     * Get recent notifications for user.
     */
    public function getRecent(int $userId, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Notification::where('user_id', $userId)
            ->recent()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark all notifications as read for user.
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Delete old notifications (older than X days).
     */
    public function deleteOld(int $days = 90): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))->delete();
    }
}
