<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\Subscriber;
use App\Models\LeadScoringRule;
use App\Models\LeadScoreHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LeadScoringService
{
    /**
     * Process a scoring event for a subscriber.
     * Returns the new score if a CRM contact exists, null otherwise.
     */
    public function processEvent(string $eventType, int $subscriberId, array $context = []): ?int
    {
        // Find the subscriber
        $subscriber = Subscriber::find($subscriberId);
        if (!$subscriber) {
            Log::debug("LeadScoring: Subscriber {$subscriberId} not found");
            return null;
        }

        // Find the corresponding CRM contact
        $contact = CrmContact::where('subscriber_id', $subscriberId)->first();
        if (!$contact) {
            Log::debug("LeadScoring: No CRM contact for subscriber {$subscriberId}");
            return null;
        }

        // Get applicable rules for this event and user
        $rules = $this->getApplicableRules($contact->user_id, $eventType);

        if ($rules->isEmpty()) {
            Log::debug("LeadScoring: No active rules for event {$eventType}");
            return $contact->score;
        }

        $totalPoints = 0;
        $appliedRules = [];

        foreach ($rules as $rule) {
            // Check if rule conditions match
            if (!$rule->matchesCondition($context)) {
                continue;
            }

            // Check cooldown
            if (!$this->checkCooldown($contact, $rule, $context)) {
                Log::debug("LeadScoring: Cooldown active for rule {$rule->id}");
                continue;
            }

            // Check daily limit
            if (!$this->checkDailyLimit($contact, $rule)) {
                Log::debug("LeadScoring: Daily limit reached for rule {$rule->id}");
                continue;
            }

            $totalPoints += $rule->points;
            $appliedRules[] = $rule;

            // For conditional rules (like specific tags/pages), only apply the first matching rule
            if ($rule->condition_field !== null) {
                break;
            }
        }

        if ($totalPoints === 0 && empty($appliedRules)) {
            return $contact->score;
        }

        // Apply the score change
        $newScore = $contact->updateScore(
            $totalPoints,
            $eventType,
            $appliedRules[0] ?? null,
            array_merge($context, [
                'applied_rules' => array_map(fn($r) => $r->id, $appliedRules),
            ])
        );

        Log::info("LeadScoring: Contact {$contact->id} score updated", [
            'event' => $eventType,
            'points' => $totalPoints,
            'old_score' => $contact->score - $totalPoints,
            'new_score' => $newScore,
        ]);

        return $newScore;
    }

    /**
     * Get applicable scoring rules for an event.
     */
    protected function getApplicableRules(int $userId, string $eventType)
    {
        // Cache rules for 5 minutes to reduce DB queries
        $cacheKey = "lead_scoring_rules:{$userId}:{$eventType}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $eventType) {
            return LeadScoringRule::forUser($userId)
                ->forEvent($eventType)
                ->active()
                ->get();
        });
    }

    /**
     * Check if cooldown period has passed for a rule.
     */
    protected function checkCooldown(CrmContact $contact, LeadScoringRule $rule, array $context): bool
    {
        if ($rule->cooldown_minutes <= 0) {
            return true;
        }

        // Build a unique cache key for this specific interaction
        $contextKey = $this->buildContextKey($context);
        $cacheKey = "scoring_cooldown:{$contact->id}:{$rule->id}:{$contextKey}";

        if (Cache::has($cacheKey)) {
            return false;
        }

        // Set cooldown
        Cache::put($cacheKey, true, now()->addMinutes($rule->cooldown_minutes));

        return true;
    }

    /**
     * Build a context key for cooldown checking.
     */
    protected function buildContextKey(array $context): string
    {
        // Use relevant context fields to create unique key
        $relevantFields = ['message_id', 'page_url', 'product_id', 'form_id', 'tag_name'];
        $keyParts = [];

        foreach ($relevantFields as $field) {
            if (isset($context[$field])) {
                $keyParts[] = "{$field}:" . md5((string) $context[$field]);
            }
        }

        return implode(':', $keyParts) ?: 'general';
    }

    /**
     * Check if daily occurrence limit has been reached.
     */
    protected function checkDailyLimit(CrmContact $contact, LeadScoringRule $rule): bool
    {
        if ($rule->max_daily_occurrences === null) {
            return true;
        }

        // Use contact owner's timezone to determine "today"
        $userTimezone = $contact->user?->timezone ?? config('app.timezone', 'UTC');
        $todayStart = Carbon::now($userTimezone)->startOfDay()->utc();
        $todayEnd = Carbon::now($userTimezone)->endOfDay()->utc();

        $todayCount = LeadScoreHistory::where('crm_contact_id', $contact->id)
            ->where('lead_scoring_rule_id', $rule->id)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        return $todayCount < $rule->max_daily_occurrences;
    }

    /**
     * Process score decay for all inactive contacts.
     * Should be called by a scheduled command.
     */
    public function processDecay(): int
    {
        $processed = 0;

        // Get all users with decay rules
        $decayRules = LeadScoringRule::whereIn('event_type', ['decay_7_days', 'decay_30_days'])
            ->active()
            ->get()
            ->groupBy('user_id');

        foreach ($decayRules as $userId => $rules) {
            foreach ($rules as $rule) {
                $inactiveDays = match ($rule->event_type) {
                    'decay_7_days' => 7,
                    'decay_30_days' => 30,
                    default => 7,
                };

                // Find contacts needing decay
                $contacts = CrmContact::forUser($userId)
                    ->needsDecay($inactiveDays)
                    ->get();

                foreach ($contacts as $contact) {
                    $contact->updateScore($rule->points, $rule->event_type, $rule, [
                        'days_inactive' => $inactiveDays,
                    ]);

                    $contact->update(['last_decay_at' => now()]);
                    $processed++;
                }
            }
        }

        Log::info("LeadScoring: Processed decay for {$processed} contacts");

        return $processed;
    }

    /**
     * Initialize default scoring rules for a new user.
     */
    public function initializeDefaultRules(int $userId): void
    {
        // Check if user already has rules
        if (LeadScoringRule::where('user_id', $userId)->exists()) {
            return;
        }

        LeadScoringRule::seedDefaultsForUser($userId);

        Log::info("LeadScoring: Initialized default rules for user {$userId}");
    }

    /**
     * Clear scoring rules cache for a user.
     */
    public function clearRulesCache(int $userId): void
    {
        foreach (LeadScoringRule::EVENT_TYPES as $eventType => $label) {
            Cache::forget("lead_scoring_rules:{$userId}:{$eventType}");
        }
    }

    /**
     * Recalculate score for a contact based on history.
     * Useful for debugging or after rule changes.
     */
    public function recalculateScore(CrmContact $contact): int
    {
        $totalPoints = $contact->scoreHistory()->sum('points_change');
        $newScore = max(0, $totalPoints);

        $contact->update(['score' => $newScore, 'score_updated_at' => now()]);

        return $newScore;
    }

    /**
     * Get scoring analytics for a user.
     */
    public function getAnalytics(int $userId, int $days = 30): array
    {
        $contactIds = CrmContact::forUser($userId)->pluck('id');

        // Use user's timezone for date calculations
        $user = User::find($userId);
        $userTimezone = $user?->timezone ?? config('app.timezone', 'UTC');
        $startDate = Carbon::now($userTimezone)->subDays($days)->startOfDay()->utc();

        $history = LeadScoreHistory::whereIn('crm_contact_id', $contactIds)
            ->where('created_at', '>=', $startDate)
            ->get();

        return [
            'total_events' => $history->count(),
            'total_points_gained' => $history->where('points_change', '>', 0)->sum('points_change'),
            'total_points_lost' => abs($history->where('points_change', '<', 0)->sum('points_change')),
            'events_by_type' => $history->groupBy('event_type')->map->count(),
            'top_scoring_events' => $history->where('points_change', '>', 0)
                ->groupBy('event_type')
                ->map(fn($items) => $items->sum('points_change'))
                ->sortDesc()
                ->take(5),
        ];
    }
}
