<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use App\Models\WebinarSession;
use App\Models\WebinarRegistration;
use App\Models\WebinarAnalytic;
use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\Tag;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WebinarService
{
    public function __construct(
        protected WebinarNotificationService $notificationService,
        protected WebinarAnalyticsService $analyticsService
    ) {}

    /**
     * Create a new webinar.
     */
    public function create(array $data, int $userId): Webinar
    {
        $data['user_id'] = $userId;
        $data['slug'] = $data['slug'] ?? Webinar::generateUniqueSlug($data['name']);
        $data['settings'] = array_merge(Webinar::DEFAULT_SETTINGS, $data['settings'] ?? []);

        $webinar = Webinar::create($data);

        // If it's an auto-webinar with schedule data, create schedule
        if ($webinar->isAutoWebinar() && isset($data['schedule'])) {
            $webinar->schedule()->create($data['schedule']);
        }

        return $webinar;
    }

    /**
     * Update a webinar.
     */
    public function update(Webinar $webinar, array $data): Webinar
    {
        // Merge settings if provided
        if (isset($data['settings'])) {
            $data['settings'] = array_merge($webinar->settings ?? [], $data['settings']);
        }

        $webinar->update($data);

        // Update schedule if provided
        if (isset($data['schedule'])) {
            if ($webinar->schedule) {
                $webinar->schedule->update($data['schedule']);
            } else {
                $webinar->schedule()->create($data['schedule']);
            }
        }

        return $webinar->fresh();
    }

    /**
     * Schedule a webinar.
     */
    public function schedule(Webinar $webinar, \DateTime $scheduledAt): bool
    {
        if ($webinar->status !== Webinar::STATUS_DRAFT) {
            return false;
        }

        $webinar->update([
            'status' => Webinar::STATUS_SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);

        // Create the first session
        WebinarSession::create([
            'webinar_id' => $webinar->id,
            'scheduled_at' => $scheduledAt,
            'status' => WebinarSession::STATUS_SCHEDULED,
        ]);

        return true;
    }

    /**
     * Start a live webinar.
     */
    public function startLive(Webinar $webinar): ?WebinarSession
    {
        if (!in_array($webinar->status, [Webinar::STATUS_SCHEDULED, Webinar::STATUS_DRAFT])) {
            return null;
        }

        DB::transaction(function () use ($webinar) {
            $webinar->update([
                'status' => Webinar::STATUS_LIVE,
                'started_at' => now(),
            ]);
        });

        // Get or create session
        $session = $webinar->sessions()->where('status', WebinarSession::STATUS_SCHEDULED)->first();

        if (!$session) {
            $session = WebinarSession::create([
                'webinar_id' => $webinar->id,
                'scheduled_at' => now(),
                'status' => WebinarSession::STATUS_SCHEDULED,
            ]);
        }

        $session->start();

        // Notify registered attendees that webinar has started
        $this->notificationService->sendWebinarStartedNotifications($webinar);

        return $session;
    }

    /**
     * End a live webinar.
     */
    public function endLive(Webinar $webinar, WebinarSession $session): bool
    {
        if ($webinar->status !== Webinar::STATUS_LIVE) {
            return false;
        }

        DB::transaction(function () use ($webinar, $session) {
            $session->end();

            $webinar->update([
                'status' => Webinar::STATUS_ENDED,
                'ended_at' => now(),
                'duration_minutes' => $webinar->started_at?->diffInMinutes(now()),
            ]);

            // Mark registered users who didn't join as missed
            $webinar->registrations()
                ->where('status', WebinarRegistration::STATUS_REGISTERED)
                ->each(fn($r) => $r->markAsMissed());
        });

        // Send replay notification if enabled
        if ($webinar->settings_with_defaults['allow_replay']) {
            $this->notificationService->scheduleReplayNotifications($webinar);
        }

        return true;
    }

    /**
     * Register a user for a webinar.
     */
    public function register(
        Webinar $webinar,
        array $data,
        ?WebinarSession $session = null
    ): ?WebinarRegistration {
        if (!$webinar->canRegister()) {
            return null;
        }

        // For auto-webinars, determine session from user selection or calculate
        $newSession = null;
        if ($webinar->isAutoWebinar() && !$session && $webinar->schedule) {
            // Use session_time from form if provided, otherwise calculate
            if (!empty($data['session_time'])) {
                $sessionTime = new \DateTime($data['session_time']);
            } else {
                $sessionTime = $webinar->schedule->calculateSessionTimeForRegistration();
            }
            $newSession = $this->getOrCreateSession($webinar, $sessionTime);
        }

        // Check if already registered
        $existing = $webinar->registrations()
            ->where('email', $data['email'])
            ->first();

        if ($existing) {
            // If user selected a new session time, update their registration
            if ($newSession && $existing->webinar_session_id !== $newSession->id) {
                $existing->update([
                    'webinar_session_id' => $newSession->id,
                    'status' => WebinarRegistration::STATUS_REGISTERED,
                    'timezone' => $data['timezone'] ?? $existing->timezone,
                ]);
                $existing->load('session');
            }
            return $existing;
        }

        // Use the calculated session
        $session = $newSession ?? $session;

        // Find or create subscriber
        $subscriber = $this->findOrCreateSubscriber($webinar, $data);

        // Create registration
        $registration = $webinar->registrations()->create([
            'webinar_session_id' => $session?->id,
            'subscriber_id' => $subscriber?->id,
            'email' => $data['email'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'custom_fields' => $data['custom_fields'] ?? null,
            'access_token' => Str::random(64),
            'status' => WebinarRegistration::STATUS_REGISTERED,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
            'utm_content' => $data['utm_content'] ?? null,
            'utm_term' => $data['utm_term'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'referrer_url' => $data['referrer_url'] ?? null,
        ]);

        // Increment counter
        $webinar->incrementRegistrations();

        // Add registration tag
        if ($subscriber && $webinar->registration_tag) {
            $this->addTagToSubscriber($subscriber, $webinar->registration_tag, $webinar->user_id);
        }

        // Track analytics
        WebinarAnalytic::track(
            $webinar,
            WebinarAnalytic::EVENT_REGISTRATION,
            $session,
            $registration,
            null,
            null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null
        );

        // Send confirmation email
        $this->notificationService->sendRegistrationConfirmation($registration);

        return $registration;
    }

    /**
     * Handle attendee joining the webinar.
     */
    public function handleJoin(
        Webinar $webinar,
        WebinarRegistration $registration,
        ?WebinarSession $session = null
    ): void {
        $registration->join();

        WebinarAnalytic::track(
            $webinar,
            WebinarAnalytic::EVENT_JOIN,
            $session,
            $registration,
            0,
            null,
            $registration->ip_address,
            $registration->user_agent
        );

        // Update session viewers if live
        if ($session && $session->isLive()) {
            $currentViewers = $session->getCurrentViewers();
            $webinar->updatePeakViewers($currentViewers);
        }

        // Add subscriber to clicked list (for tracking who viewed the webinar page)
        $this->addSubscriberToList($registration, $webinar->clickedList);
    }

    /**
     * Handle attendee leaving the webinar.
     */
    public function handleLeave(
        Webinar $webinar,
        WebinarRegistration $registration,
        ?WebinarSession $session = null,
        ?int $videoTimeSeconds = null
    ): void {
        $registration->leave();

        WebinarAnalytic::track(
            $webinar,
            WebinarAnalytic::EVENT_LEAVE,
            $session,
            $registration,
            $videoTimeSeconds,
            ['watch_time_seconds' => $registration->watch_time_seconds]
        );

        // Check if subscriber should be added to attended list based on watch time
        $minMinutes = $webinar->attended_min_minutes ?? 5;
        $watchMinutes = floor($registration->watch_time_seconds / 60);

        if ($watchMinutes >= $minMinutes) {
            $this->addSubscriberToList($registration, $webinar->attendedList);
        }
    }

    /**
     * Get or create a webinar session.
     */
    protected function getOrCreateSession(Webinar $webinar, \DateTime $scheduledAt): WebinarSession
    {
        // Find existing session at this time
        $session = $webinar->sessions()
            ->whereDate('scheduled_at', $scheduledAt->format('Y-m-d'))
            ->whereTime('scheduled_at', $scheduledAt->format('H:i:s'))
            ->first();

        if ($session) {
            return $session;
        }

        // Create new session
        $sessionNumber = $webinar->sessions()->count() + 1;

        return WebinarSession::create([
            'webinar_id' => $webinar->id,
            'scheduled_at' => $scheduledAt,
            'status' => WebinarSession::STATUS_SCHEDULED,
            'is_replay' => $webinar->isAutoWebinar(),
            'session_number' => $sessionNumber,
        ]);
    }

    /**
     * Find or create a subscriber from registration data.
     */
    protected function findOrCreateSubscriber(Webinar $webinar, array $data): ?Subscriber
    {
        if (!$webinar->target_list_id) {
            return null;
        }

        $list = ContactList::find($webinar->target_list_id);
        if (!$list) {
            return null;
        }

        // Find existing subscriber
        $subscriber = Subscriber::where('email', $data['email'])
            ->where('user_id', $webinar->user_id)
            ->first();

        if (!$subscriber) {
            $subscriber = Subscriber::create([
                'user_id' => $webinar->user_id,
                'email' => $data['email'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);
        }

        // Attach to list if not already
        if (!$subscriber->contactLists()->where('contact_list_id', $list->id)->exists()) {
            $subscriber->contactLists()->attach($list->id, [
                'source' => 'webinar',
                'created_at' => now(),
            ]);
        }

        return $subscriber;
    }

    /**
     * Add tag to subscriber.
     */
    protected function addTagToSubscriber(Subscriber $subscriber, string $tagName, int $userId): void
    {
        $tag = Tag::firstOrCreate(
            ['name' => $tagName, 'user_id' => $userId],
            ['name' => $tagName, 'user_id' => $userId]
        );

        $subscriber->tags()->syncWithoutDetaching([$tag->id]);
    }

    /**
     * Add subscriber from registration to a contact list.
     */
    protected function addSubscriberToList(WebinarRegistration $registration, ?ContactList $list): void
    {
        if (!$list || !$registration->subscriber_id) {
            return;
        }

        $subscriber = $registration->subscriber;
        if (!$subscriber) {
            return;
        }

        // Attach to list if not already
        if (!$subscriber->contactLists()->where('contact_list_id', $list->id)->exists()) {
            $subscriber->contactLists()->attach($list->id, [
                'source' => 'webinar',
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }
    }

    /**
     * Duplicate a webinar.
     */
    public function duplicate(Webinar $webinar, ?string $newName = null): Webinar
    {
        return $webinar->duplicate($newName);
    }

    /**
     * Delete a webinar and all related data.
     */
    public function delete(Webinar $webinar): bool
    {
        // All related data will be cascade deleted due to foreign keys
        return $webinar->delete();
    }

    /**
     * Publish webinar as replay.
     */
    public function publishAsReplay(Webinar $webinar): bool
    {
        return $webinar->publish();
    }

    /**
     * Get upcoming webinars for a user.
     */
    public function getUpcomingWebinars(int $userId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return Webinar::forUser($userId)
            ->scheduled()
            ->upcoming()
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get webinar statistics.
     */
    public function getStats(Webinar $webinar): array
    {
        return $this->analyticsService->getWebinarStats($webinar);
    }
}
