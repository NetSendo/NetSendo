<?php

namespace App\Http\Controllers;

use App\Models\PageVisit;
use App\Models\Subscriber;
use App\Events\PageVisited;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PageVisitController extends Controller
{
    /**
     * Track a page visit.
     * Called by the tracking script on external pages.
     */
    public function track(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'page_url' => 'required|string|max:2048',
            'page_title' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:2048',
            'visitor_token' => 'nullable|string|max:64',
            'time_on_page_seconds' => 'nullable|integer|min:0',
        ]);

        try {
            // Try to identify subscriber from token cookie
            $subscriberId = null;
            $visitorToken = $validated['visitor_token'] ?? $request->cookie('ns_visitor');

            if ($visitorToken) {
                // Check if we can link this visitor to a subscriber
                $subscriberId = $this->identifySubscriber($visitorToken, $validated['user_id']);
            }

            // Generate new visitor token if not present
            if (!$visitorToken) {
                $visitorToken = Str::uuid()->toString();
            }

            $pageVisit = PageVisit::create([
                'subscriber_id' => $subscriberId,
                'user_id' => $validated['user_id'],
                'page_url' => $validated['page_url'],
                'page_title' => $validated['page_title'] ?? null,
                'referrer' => $validated['referrer'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'visitor_token' => $visitorToken,
                'time_on_page_seconds' => $validated['time_on_page_seconds'] ?? null,
                'visited_at' => now(),
            ]);

            // Dispatch event if subscriber is identified
            if ($subscriberId) {
                event(new PageVisited(
                    $subscriberId,
                    $validated['page_url'],
                    $validated['user_id'],
                    $validated['page_title'] ?? null,
                    $visitorToken
                ));
            }

            return response()
                ->json([
                    'success' => true,
                    'visit_id' => $pageVisit->id,
                ])
                ->cookie('ns_visitor', $visitorToken, 60 * 24 * 365); // 1 year

        } catch (\Exception $e) {
            Log::error('Failed to track page visit: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to track visit'], 500);
        }
    }

    /**
     * Link a visitor token to a subscriber.
     * Called when a subscriber clicks a tracked link in an email.
     */
    public function linkVisitor(Request $request)
    {
        $validated = $request->validate([
            'visitor_token' => 'required|string|max:64',
            'subscriber_id' => 'required|integer|exists:subscribers,id',
        ]);

        try {
            // Update all past visits with this token
            $updated = PageVisit::linkVisitorToSubscriber(
                $validated['visitor_token'],
                $validated['subscriber_id']
            );

            return response()->json([
                'success' => true,
                'linked_visits' => $updated,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to link visitor: ' . $e->getMessage());
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    /**
     * Generate tracking script for a user.
     */
    public function getTrackingScript($userId, Request $request)
    {
        $baseUrl = config('app.url');

        $script = <<<JS
(function() {
    var NS_USER_ID = {$userId};
    var NS_ENDPOINT = '{$baseUrl}/t/page';
    
    var startTime = Date.now();
    var visitorToken = getCookie('ns_visitor') || generateUUID();
    
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
        return null;
    }
    
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    function sendPageVisit(timeOnPage) {
        var data = {
            user_id: NS_USER_ID,
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            visitor_token: visitorToken,
            time_on_page_seconds: timeOnPage
        };
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(NS_ENDPOINT, JSON.stringify(data));
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', NS_ENDPOINT, false);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(data));
        }
    }
    
    // Track initial page visit
    sendPageVisit(0);
    
    // Track time on page when leaving
    window.addEventListener('beforeunload', function() {
        var timeOnPage = Math.round((Date.now() - startTime) / 1000);
        sendPageVisit(timeOnPage);
    });
    
    // Also track on visibility change (for mobile)
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            var timeOnPage = Math.round((Date.now() - startTime) / 1000);
            sendPageVisit(timeOnPage);
        }
    });
})();
JS;

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Try to identify a subscriber from visitor token.
     */
    protected function identifySubscriber(string $visitorToken, int $userId): ?int
    {
        // Check if we have a previous visit with this token that has a subscriber
        $previousVisit = PageVisit::where('visitor_token', $visitorToken)
            ->where('user_id', $userId)
            ->whereNotNull('subscriber_id')
            ->first();

        return $previousVisit?->subscriber_id;
    }

    /**
     * Get page visit statistics for a user.
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        $days = $request->input('days', 7);

        return response()->json(
            PageVisit::getStatsForUser($userId, $days)
        );
    }
}
