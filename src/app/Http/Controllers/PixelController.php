<?php

namespace App\Http\Controllers;

use App\Events\PixelPageVisited;
use App\Events\PixelProductViewed;
use App\Events\PixelAddToCart;
use App\Events\PixelCheckoutStarted;
use App\Models\PixelEvent;
use App\Models\Subscriber;
use App\Models\SubscriberDevice;
use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\LiveVisitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PixelController extends Controller
{
    protected DeviceFingerprintService $fingerprintService;
    protected LiveVisitorService $liveVisitorService;

    public function __construct(
        DeviceFingerprintService $fingerprintService,
        LiveVisitorService $liveVisitorService
    ) {
        $this->fingerprintService = $fingerprintService;
        $this->liveVisitorService = $liveVisitorService;
    }

    /**
     * Serve the pixel JavaScript
     */
    public function script(int $userId, Request $request)
    {
        // Verify user exists
        $user = User::find($userId);
        if (!$user) {
            return response('console.error("NetSendo Pixel: Invalid user");', 404)
                ->header('Content-Type', 'application/javascript');
        }

        $baseUrl = config('app.url');

        $script = $this->generatePixelScript($userId, $baseUrl);

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600'); // 1 hour cache
    }

    /**
     * Track a single event
     */
    public function trackEvent(Request $request)
    {
        // Debug logging for pixel requests
        Log::debug('Pixel trackEvent called', [
            'content_type' => $request->header('Content-Type'),
            'request_data' => $request->all(),
            'raw_content' => $request->getContent(),
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'visitor_token' => 'required|string|max:64',
            'event_type' => 'required|string|max:50',
            'page_url' => 'required|string|max:2048',
            'page_title' => 'nullable|string|max:255',
            'referrer' => 'nullable|string|max:2048',
            // E-commerce fields
            'product_id' => 'nullable|string|max:100',
            'product_name' => 'nullable|string|max:255',
            'product_category' => 'nullable|string|max:255',
            'product_price' => 'nullable|numeric|min:0',
            'product_currency' => 'nullable|string|max:3',
            'cart_value' => 'nullable|numeric|min:0',
            // Engagement
            'time_on_page' => 'nullable|integer|min:0',
            'scroll_depth' => 'nullable|integer|min:0|max:100',
            // Device info
            'screen_resolution' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
            // Custom data
            'custom_data' => 'nullable|array',
        ]);

        try {
            // Build device info
            $deviceInfo = $this->fingerprintService->buildDeviceInfo(
                $request->userAgent() ?? '',
                $validated['screen_resolution'] ?? null,
                $validated['language'] ?? null,
                $validated['timezone'] ?? null,
                $request->ip()
            );

            // Find or create device
            $device = SubscriberDevice::findOrCreateForVisitor(
                $validated['visitor_token'],
                $validated['user_id'],
                $deviceInfo
            );

            // Log the event
            $event = PixelEvent::logEvent($device, $validated['event_type'], [
                'page_url' => $validated['page_url'],
                'page_title' => $validated['page_title'] ?? null,
                'referrer' => $validated['referrer'] ?? null,
                'product_id' => $validated['product_id'] ?? null,
                'product_name' => $validated['product_name'] ?? null,
                'product_category' => $validated['product_category'] ?? null,
                'product_price' => $validated['product_price'] ?? null,
                'product_currency' => $validated['product_currency'] ?? 'PLN',
                'cart_value' => $validated['cart_value'] ?? null,
                'time_on_page' => $validated['time_on_page'] ?? null,
                'scroll_depth' => $validated['scroll_depth'] ?? null,
                'custom_data' => $validated['custom_data'] ?? null,
                'ip_address' => $request->ip(),
            ]);

            // Dispatch events for automation triggers if subscriber is identified
            if ($device->subscriber_id) {
                $this->dispatchAutomationEvents($device, $event, $validated);
            }

            // Broadcast live visitor for real-time dashboard
            if ($validated['event_type'] === PixelEvent::TYPE_PAGE_VIEW) {
                $this->liveVisitorService->markActive(
                    $validated['user_id'],
                    $validated['visitor_token'],
                    $validated['page_url'],
                    $validated['page_title'] ?? null,
                    $deviceInfo['device_type'] ?? 'desktop',
                    $deviceInfo['browser'] ?? null
                );
            }

            return response()->json([
                'success' => true,
                'event_id' => $event->id,
                'visitor_token' => $device->visitor_token,
            ]);

        } catch (\Exception $e) {
            Log::error('Pixel tracking error', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return response()->json(['error' => 'Failed to track event'], 500);
        }
    }

    /**
     * Handle batch events (multiple events at once)
     */
    public function batchEvents(Request $request)
    {
        // Debug logging for batch pixel requests
        Log::debug('Pixel batchEvents called', [
            'content_type' => $request->header('Content-Type'),
            'request_data' => $request->all(),
            'raw_content' => substr($request->getContent(), 0, 1000),
            'ip' => $request->ip(),
        ]);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'visitor_token' => 'required|string|max:64',
            'events' => 'required|array|max:50',
            'events.*.event_type' => 'required|string|max:50',
            'events.*.page_url' => 'required|string|max:2048',
            'events.*.page_title' => 'nullable|string|max:255',
            'events.*.product_id' => 'nullable|string|max:100',
            'events.*.product_name' => 'nullable|string|max:255',
            'events.*.time_on_page' => 'nullable|integer|min:0',
            // Device info (sent once)
            'screen_resolution' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:10',
            'timezone' => 'nullable|string|max:100',
        ]);

        try {
            // Build device info
            $deviceInfo = $this->fingerprintService->buildDeviceInfo(
                $request->userAgent() ?? '',
                $validated['screen_resolution'] ?? null,
                $validated['language'] ?? null,
                $validated['timezone'] ?? null,
                $request->ip()
            );

            // Find or create device
            $device = SubscriberDevice::findOrCreateForVisitor(
                $validated['visitor_token'],
                $validated['user_id'],
                $deviceInfo
            );

            $eventIds = [];

            foreach ($validated['events'] as $eventData) {
                $event = PixelEvent::logEvent($device, $eventData['event_type'], [
                    'page_url' => $eventData['page_url'],
                    'page_title' => $eventData['page_title'] ?? null,
                    'product_id' => $eventData['product_id'] ?? null,
                    'product_name' => $eventData['product_name'] ?? null,
                    'time_on_page' => $eventData['time_on_page'] ?? null,
                    'ip_address' => $request->ip(),
                ]);

                $eventIds[] = $event->id;

                if ($device->subscriber_id) {
                    $this->dispatchAutomationEvents($device, $event, $eventData);
                }
            }

            return response()->json([
                'success' => true,
                'event_count' => count($eventIds),
                'visitor_token' => $device->visitor_token,
            ]);

        } catch (\Exception $e) {
            Log::error('Pixel batch tracking error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to track events'], 500);
        }
    }

    /**
     * Identify a visitor with subscriber email
     */
    public function identify(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'visitor_token' => 'required|string|max:64',
            'email' => 'required|email',
        ]);

        try {
            $email = strtolower(trim($validated['email']));

            // Find subscriber
            $subscriber = Subscriber::where('email', $email)
                ->where('user_id', $validated['user_id'])
                ->first();

            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscriber not found',
                ]);
            }

            // Link all devices with this visitor token to the subscriber
            $linkedCount = SubscriberDevice::linkVisitorToSubscriber(
                $validated['visitor_token'],
                $validated['user_id'],
                $subscriber->id
            );

            return response()->json([
                'success' => true,
                'subscriber_id' => $subscriber->id,
                'linked_devices' => $linkedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Pixel identify error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to identify visitor'], 500);
        }
    }

    /**
     * Dispatch automation events based on pixel event type
     */
    protected function dispatchAutomationEvents(
        SubscriberDevice $device,
        PixelEvent $event,
        array $data
    ): void {
        try {
            switch ($event->event_type) {
                case PixelEvent::TYPE_PAGE_VIEW:
                    event(new PixelPageVisited(
                        $device->subscriber_id,
                        $device->id,
                        $data['page_url'],
                        $device->user_id,
                        $data['page_title'] ?? null
                    ));
                    break;

                case PixelEvent::TYPE_PRODUCT_VIEW:
                    event(new PixelProductViewed(
                        $device->subscriber_id,
                        $device->id,
                        $data['page_url'],
                        $device->user_id,
                        $data['product_id'] ?? null,
                        $data['product_name'] ?? null,
                        isset($data['product_price']) ? (float)$data['product_price'] : null
                    ));
                    break;

                case PixelEvent::TYPE_ADD_TO_CART:
                    event(new PixelAddToCart(
                        $device->subscriber_id,
                        $device->id,
                        $device->user_id,
                        $data['product_id'] ?? null,
                        $data['product_name'] ?? null,
                        isset($data['cart_value']) ? (float)$data['cart_value'] : null
                    ));
                    break;

                case PixelEvent::TYPE_CHECKOUT_STARTED:
                    event(new PixelCheckoutStarted(
                        $device->subscriber_id,
                        $device->id,
                        $device->user_id,
                        isset($data['cart_value']) ? (float)$data['cart_value'] : null
                    ));
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Failed to dispatch pixel automation event', [
                'error' => $e->getMessage(),
                'event_type' => $event->event_type,
            ]);
        }
    }

    /**
     * Generate the pixel JavaScript code
     */
    protected function generatePixelScript(int $userId, string $baseUrl): string
    {
        return <<<JS
/**
 * NetSendo Pixel v1.0
 * Professional tracking for marketing automation
 */
(function(window, document) {
    'use strict';

    var NS = window.NetSendo = window.NetSendo || [];

    // Prevent double initialization
    if (NS._initialized) return;

    var config = {
        userId: {$userId},
        apiUrl: '{$baseUrl}/t/pixel',
        batchSize: 10,
        batchTimeout: 5000,
        debug: false
    };

    var state = {
        visitorToken: null,
        eventQueue: [],
        batchTimer: null,
        startTime: Date.now(),
        maxScrollDepth: 0
    };

    // Utility functions
    function log() {
        if (config.debug && console && console.log) {
            console.log.apply(console, ['[NetSendo]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function getVisitorToken() {
        if (state.visitorToken) return state.visitorToken;

        // Try localStorage first
        try {
            state.visitorToken = localStorage.getItem('ns_visitor');
        } catch (e) {}

        // Fall back to cookie
        if (!state.visitorToken) {
            var match = document.cookie.match(/ns_visitor=([^;]+)/);
            if (match) state.visitorToken = match[1];
        }

        // Generate new token if none exists
        if (!state.visitorToken) {
            state.visitorToken = generateUUID();
            saveVisitorToken(state.visitorToken);
        }

        return state.visitorToken;
    }

    function saveVisitorToken(token) {
        try {
            localStorage.setItem('ns_visitor', token);
        } catch (e) {}

        // Also save as cookie (1 year expiry)
        var expires = new Date();
        expires.setFullYear(expires.getFullYear() + 1);
        document.cookie = 'ns_visitor=' + token + '; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
    }

    function getDeviceInfo() {
        return {
            screen_resolution: screen.width + 'x' + screen.height,
            language: navigator.language || navigator.userLanguage,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
    }

    function sendRequest(endpoint, data, callback) {
        var fullData = Object.assign({
            user_id: config.userId,
            visitor_token: getVisitorToken()
        }, data, getDeviceInfo());

        // Use sendBeacon if available for reliability
        if (navigator.sendBeacon && endpoint !== '/identify') {
            var blob = new Blob([JSON.stringify(fullData)], { type: 'application/json' });
            navigator.sendBeacon(config.apiUrl + endpoint, blob);
            if (callback) callback(true);
            return;
        }

        // Fallback to XHR
        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.apiUrl + endpoint, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && callback) {
                callback(xhr.status >= 200 && xhr.status < 300);
            }
        };
        xhr.send(JSON.stringify(fullData));
    }

    function queueEvent(eventType, eventData) {
        var event = Object.assign({
            event_type: eventType,
            page_url: window.location.href,
            page_title: document.title
        }, eventData || {});

        state.eventQueue.push(event);
        log('Event queued:', eventType, event);

        // Send immediately if queue is full
        if (state.eventQueue.length >= config.batchSize) {
            flushEvents();
        } else if (!state.batchTimer) {
            // Set timer for batch send
            state.batchTimer = setTimeout(flushEvents, config.batchTimeout);
        }
    }

    function flushEvents() {
        if (state.batchTimer) {
            clearTimeout(state.batchTimer);
            state.batchTimer = null;
        }

        if (state.eventQueue.length === 0) return;

        var events = state.eventQueue.splice(0, config.batchSize);

        if (events.length === 1) {
            sendRequest('/event', events[0]);
        } else {
            sendRequest('/batch', { events: events });
        }
    }

    function trackPageView() {
        queueEvent('page_view', {
            referrer: document.referrer
        });
    }

    function trackTimeOnPage() {
        var timeOnPage = Math.round((Date.now() - state.startTime) / 1000);
        if (timeOnPage > 0) {
            queueEvent('page_view', {
                time_on_page: timeOnPage,
                scroll_depth: state.maxScrollDepth
            });
            flushEvents();
        }
    }

    function trackScrollDepth() {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var docHeight = Math.max(
            document.body.scrollHeight,
            document.documentElement.scrollHeight
        );
        var winHeight = window.innerHeight;
        var scrollPercent = Math.round((scrollTop + winHeight) / docHeight * 100);

        if (scrollPercent > state.maxScrollDepth) {
            state.maxScrollDepth = Math.min(scrollPercent, 100);
        }
    }

    // Public API
    NS.push = function(args) {
        if (!Array.isArray(args)) return;

        var command = args[0];
        var data = args[1] || {};

        switch (command) {
            case 'init':
                if (data.userId) config.userId = data.userId;
                if (data.apiUrl) config.apiUrl = data.apiUrl;
                if (data.debug) config.debug = true;
                log('Initialized with config:', config);
                break;

            case 'track':
                var eventType = args[1];
                var eventData = args[2] || {};
                queueEvent(eventType, eventData);
                break;

            case 'identify':
                if (data.email) {
                    sendRequest('/identify', { email: data.email }, function(success) {
                        log('Identify result:', success);
                    });
                }
                break;

            case 'page_view':
                trackPageView();
                break;

            case 'debug':
                config.debug = true;
                break;
        }
    };

    // Process any commands queued before script loaded
    var queue = NS.slice ? NS.slice(0) : [];
    NS.length = 0;
    queue.forEach(function(args) {
        NS.push(args);
    });

    // Auto-track page view
    trackPageView();

    // Track scroll depth
    window.addEventListener('scroll', trackScrollDepth, { passive: true });

    // Track time on page when leaving
    window.addEventListener('beforeunload', trackTimeOnPage);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            trackTimeOnPage();
        }
    });

    NS._initialized = true;
    log('Pixel loaded for user:', config.userId);

})(window, document);
JS;
    }
}
