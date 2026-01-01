<?php

namespace App\Services\Webinar;

use App\Models\Webinar;
use App\Models\WebinarSession;
use App\Models\WebinarAnalytic;

class WebinarAnalyticsService
{
    public function getWebinarStats(Webinar $webinar): array
    {
        return WebinarAnalytic::getWebinarStats($webinar);
    }

    public function getConversionFunnel(Webinar $webinar): array
    {
        return WebinarAnalytic::getConversionFunnel($webinar);
    }

    public function getEngagementTimeline(Webinar $webinar, ?WebinarSession $session = null): array
    {
        return WebinarAnalytic::getEngagementTimeline($webinar, $session);
    }

    public function getDeviceBreakdown(Webinar $webinar): array
    {
        return WebinarAnalytic::getDeviceBreakdown($webinar);
    }

    public function getRevenueStats(Webinar $webinar): array
    {
        $purchases = WebinarAnalytic::where('webinar_id', $webinar->id)
            ->where('event_type', WebinarAnalytic::EVENT_PURCHASE)
            ->get();

        return [
            'total_purchases' => $purchases->count(),
            'total_revenue' => $purchases->sum(fn($p) => $p->metadata['amount'] ?? 0),
            'average_order_value' => $purchases->count() > 0
                ? $purchases->sum(fn($p) => $p->metadata['amount'] ?? 0) / $purchases->count()
                : 0,
        ];
    }

    public function getAttendeeEngagement(Webinar $webinar): array
    {
        $registrations = $webinar->registrations()->get();

        return [
            'total_registrations' => $registrations->count(),
            'attended' => $registrations->where('status', 'attended')->count(),
            'missed' => $registrations->where('status', 'missed')->count(),
            'partial' => $registrations->where('status', 'partial')->count(),
            'average_watch_time' => $registrations->avg('watch_time_seconds') ?? 0,
            'average_engagement_score' => $registrations->avg('engagement_score') ?? 0,
        ];
    }
}
