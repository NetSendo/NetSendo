<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Message;
use App\Models\EmailOpen;
use App\Models\EmailClick;
use App\Models\MessageQueueEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CampaignStatsService
{
    /**
     * Get aggregated statistics for all messages tagged with a specific tag.
     */
    public function getTagStats(Tag $tag): array
    {
        $messages = $tag->messages()->with(['queueEntries'])->get();

        if ($messages->isEmpty()) {
            return $this->emptyStats();
        }

        $messageIds = $messages->pluck('id')->toArray();

        // Get total sent
        $totalSent = MessageQueueEntry::whereIn('message_id', $messageIds)
            ->where('status', MessageQueueEntry::STATUS_SENT)
            ->count();

        // Get unique opens
        $totalOpens = EmailOpen::whereIn('message_id', $messageIds)
            ->distinct('subscriber_id')
            ->count('subscriber_id');

        // Get unique clicks
        $totalClicks = EmailClick::whereIn('message_id', $messageIds)
            ->distinct('subscriber_id')
            ->count('subscriber_id');

        // Get failed/bounced
        $totalFailed = MessageQueueEntry::whereIn('message_id', $messageIds)
            ->where('status', MessageQueueEntry::STATUS_FAILED)
            ->count();

        // Calculate rates
        $openRate = $totalSent > 0 ? round(($totalOpens / $totalSent) * 100, 2) : 0;
        $clickRate = $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 2) : 0;
        $bounceRate = $totalSent > 0 ? round(($totalFailed / $totalSent) * 100, 2) : 0;
        $ctr = $totalOpens > 0 ? round(($totalClicks / $totalOpens) * 100, 2) : 0;

        return [
            'messages_count' => $messages->count(),
            'total_sent' => $totalSent,
            'total_opens' => $totalOpens,
            'total_clicks' => $totalClicks,
            'total_failed' => $totalFailed,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'bounce_rate' => $bounceRate,
            'ctr' => $ctr,
            'status' => $this->getCampaignStatus($tag, $messages),
            'date_range' => $this->getDateRange($messages),
        ];
    }

    /**
     * Get detailed statistics for each message in a tag.
     */
    public function getTagMessagesStats(Tag $tag): Collection
    {
        return $tag->messages()
            ->with(['mailbox', 'contactLists'])
            ->orderBy('send_at', 'desc')
            ->get()
            ->map(function ($message) {
                $queueStats = $message->getQueueStats();
                $sent = $queueStats['sent'] ?? 0;

                $opens = EmailOpen::where('message_id', $message->id)
                    ->distinct('subscriber_id')
                    ->count('subscriber_id');

                $clicks = EmailClick::where('message_id', $message->id)
                    ->distinct('subscriber_id')
                    ->count('subscriber_id');

                return [
                    'id' => $message->id,
                    'subject' => $message->subject,
                    'status' => $message->status,
                    'type' => $message->type,
                    'channel' => $message->channel,
                    'send_at' => $message->send_at?->format('Y-m-d H:i'),
                    'sent' => $sent,
                    'opens' => $opens,
                    'clicks' => $clicks,
                    'open_rate' => $sent > 0 ? round(($opens / $sent) * 100, 2) : 0,
                    'click_rate' => $sent > 0 ? round(($clicks / $sent) * 100, 2) : 0,
                    'failed' => $queueStats['failed'] ?? 0,
                    'mailbox' => $message->mailbox?->from_email,
                    'lists' => $message->contactLists->pluck('name')->toArray(),
                ];
            });
    }

    /**
     * Determine campaign status based on message dates and statuses.
     */
    public function getCampaignStatus(Tag $tag, ?Collection $messages = null): string
    {
        $messages = $messages ?? $tag->messages;

        if ($messages->isEmpty()) {
            return 'empty';
        }

        $now = Carbon::now();
        $hasSent = $messages->where('status', 'sent')->isNotEmpty();
        $hasScheduled = $messages->where('status', 'scheduled')->isNotEmpty();
        $hasDraft = $messages->where('status', 'draft')->isNotEmpty();
        $hasFuture = $messages->filter(fn($m) => $m->send_at && $m->send_at->isFuture())->isNotEmpty();

        if ($hasScheduled || $hasFuture) {
            if ($hasSent) {
                return 'ongoing'; // Has both sent and scheduled messages
            }
            return 'future'; // Only scheduled/future messages
        }

        if ($hasSent) {
            return 'past'; // Only sent messages
        }

        return 'draft'; // Only drafts
    }

    /**
     * Get the date range of messages in the campaign.
     */
    protected function getDateRange(Collection $messages): array
    {
        $dates = $messages
            ->filter(fn($m) => $m->send_at)
            ->pluck('send_at');

        if ($dates->isEmpty()) {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => $dates->min()->format('Y-m-d'),
            'end' => $dates->max()->format('Y-m-d'),
        ];
    }

    /**
     * Calculate trends by comparing with user's other campaigns.
     */
    public function calculateTrends(Tag $tag, int $userId): array
    {
        $currentStats = $this->getTagStats($tag);

        // Get average stats from other tags with messages
        $otherTags = Tag::where('user_id', $userId)
            ->where('id', '!=', $tag->id)
            ->whereHas('messages')
            ->with('messages')
            ->get();

        if ($otherTags->isEmpty()) {
            return [
                'open_rate_trend' => null,
                'click_rate_trend' => null,
                'has_comparison' => false,
            ];
        }

        $avgOpenRate = 0;
        $avgClickRate = 0;
        $count = 0;

        foreach ($otherTags as $otherTag) {
            $stats = $this->getTagStats($otherTag);
            if ($stats['total_sent'] > 0) {
                $avgOpenRate += $stats['open_rate'];
                $avgClickRate += $stats['click_rate'];
                $count++;
            }
        }

        if ($count === 0) {
            return [
                'open_rate_trend' => null,
                'click_rate_trend' => null,
                'has_comparison' => false,
            ];
        }

        $avgOpenRate /= $count;
        $avgClickRate /= $count;

        return [
            'open_rate_trend' => round($currentStats['open_rate'] - $avgOpenRate, 2),
            'click_rate_trend' => round($currentStats['click_rate'] - $avgClickRate, 2),
            'avg_open_rate' => round($avgOpenRate, 2),
            'avg_click_rate' => round($avgClickRate, 2),
            'has_comparison' => true,
        ];
    }

    /**
     * Get all tags with message counts and basic stats for a user.
     */
    public function getAllTagsWithStats(int $userId): Collection
    {
        return Tag::where('user_id', $userId)
            ->withCount('messages')
            ->having('messages_count', '>', 0)
            ->get()
            ->map(function ($tag) {
                $stats = $this->getTagStats($tag);
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                    'messages_count' => $tag->messages_count,
                    'total_sent' => $stats['total_sent'],
                    'open_rate' => $stats['open_rate'],
                    'click_rate' => $stats['click_rate'],
                    'status' => $stats['status'],
                    'date_range' => $stats['date_range'],
                ];
            });
    }

    /**
     * Return empty stats structure.
     */
    protected function emptyStats(): array
    {
        return [
            'messages_count' => 0,
            'total_sent' => 0,
            'total_opens' => 0,
            'total_clicks' => 0,
            'total_failed' => 0,
            'open_rate' => 0,
            'click_rate' => 0,
            'bounce_rate' => 0,
            'ctr' => 0,
            'status' => 'empty',
            'date_range' => ['start' => null, 'end' => null],
        ];
    }
}
