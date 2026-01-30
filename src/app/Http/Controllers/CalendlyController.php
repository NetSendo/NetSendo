<?php

namespace App\Http\Controllers;

use App\Models\CalendlyIntegration;
use App\Models\CalendlyEvent;
use App\Models\ContactList;
use App\Models\Tag;
use App\Models\User;
use App\Services\CalendlyOAuthService;
use App\Services\CalendlyService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CalendlyController extends Controller
{
    public function __construct(
        private CalendlyOAuthService $oauthService,
        private CalendlyService $calendlyService
    ) {}

    /**
     * Display Calendly integration settings.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $integrations = CalendlyIntegration::forUser($user->id)
            ->with(['events' => function ($query) {
                $query->latest()->limit(10);
            }])
            ->get()
            ->map(function ($integration) {
                return [
                    'id' => $integration->id,
                    'calendly_user_email' => $integration->calendly_user_email,
                    'calendly_user_name' => $integration->calendly_user_name,
                    'is_active' => $integration->is_active,
                    'has_credentials' => !empty($integration->client_id) && !empty($integration->client_secret),
                    'settings' => $integration->settings ?? CalendlyIntegration::getDefaultSettings(),
                    'event_types' => $integration->event_types ?? [],
                    'webhook_id' => $integration->webhook_id,
                    'created_at' => $integration->created_at,
                    'recent_events' => $integration->events->map(fn($e) => [
                        'id' => $e->id,
                        'event_type_name' => $e->event_type_name,
                        'invitee_email' => $e->invitee_email,
                        'invitee_name' => $e->invitee_name,
                        'start_time' => $e->start_time,
                        'status' => $e->status,
                    ]),
                ];
            });

        $mailingLists = ContactList::forUser($user->id)
            ->select('id', 'name', 'type')
            ->get();

        $tags = Tag::where('user_id', $user->id)
            ->select('id', 'name')
            ->get();

        // Get team members: admin and all their team members
        $teamMembers = collect([$user])->merge($user->teamMembers)
            ->map(fn($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ]);

        return Inertia::render('Settings/Calendly/Index', [
            'integrations' => $integrations,
            'mailingLists' => $mailingLists,
            'tags' => $tags,
            'teamMembers' => $teamMembers,
            'webhookUrl' => route('api.webhooks.calendly'),
        ]);
    }

    /**
     * Save API credentials and initiate OAuth connection.
     */
    public function connect(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'client_id' => 'required|string|min:10',
            'client_secret' => 'required|string|min:10',
        ]);

        $user = $request->user();

        // Check if there's already a pending integration without tokens
        $integration = CalendlyIntegration::where('user_id', $user->id)
            ->whereNull('access_token')
            ->first();

        if (!$integration) {
            // Create new integration with credentials
            $integration = CalendlyIntegration::create([
                'user_id' => $user->id,
                'client_id' => $validated['client_id'],
                'client_secret' => $validated['client_secret'],
                'access_token' => null,
                'refresh_token' => null,
                'is_active' => false,
                'settings' => CalendlyIntegration::getDefaultSettings(),
            ]);
        } else {
            // Update existing integration with new credentials
            $integration->update([
                'client_id' => $validated['client_id'],
                'client_secret' => $validated['client_secret'],
            ]);
        }

        // Generate state for CSRF protection - include integration ID
        $state = Str::random(40);
        session([
            'calendly_oauth_state' => $state,
            'calendly_integration_id' => $integration->id,
        ]);

        $redirectUri = $this->oauthService->getRedirectUri();
        $authUrl = $this->oauthService->getAuthorizationUrl(
            $validated['client_id'],
            $redirectUri,
            $state
        );

        // Use Inertia::location() for external redirects to avoid CORS issues
        // Inertia makes XHR requests, so we need to tell it to do a full page redirect
        return Inertia::location($authUrl);
    }

    /**
     * Handle OAuth callback from Calendly.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Verify state
        $state = $request->query('state');
        $storedState = session('calendly_oauth_state');
        $integrationId = session('calendly_integration_id');

        if (!$state || $state !== $storedState) {
            return redirect()->route('settings.calendly.index')
                ->with('error', __('Invalid OAuth state. Please try again.'));
        }

        session()->forget(['calendly_oauth_state', 'calendly_integration_id']);

        // Check for errors
        if ($request->has('error')) {
            return redirect()->route('settings.calendly.index')
                ->with('error', __('Calendly authorization was denied: ') . $request->query('error_description', $request->query('error')));
        }

        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('settings.calendly.index')
                ->with('error', __('No authorization code received from Calendly.'));
        }

        // Get the integration
        $integration = CalendlyIntegration::find($integrationId);

        if (!$integration || $integration->user_id !== $request->user()->id) {
            return redirect()->route('settings.calendly.index')
                ->with('error', __('Integration not found.'));
        }

        try {
            // Exchange code for tokens using stored credentials
            $redirectUri = $this->oauthService->getRedirectUri();
            $tokens = $this->oauthService->exchangeCodeForTokens(
                $code,
                $integration->client_id,
                $integration->client_secret,
                $redirectUri
            );

            // Update integration with tokens
            $integration->update([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'is_active' => true,
            ]);

            // Reload to get fresh data
            $integration->refresh();

            // Fetch and store user info
            $user = $this->calendlyService->getCurrentUser($integration);

            $integration->update([
                'calendly_user_uri' => $user['uri'],
                'calendly_organization_uri' => $user['current_organization'],
                'calendly_user_email' => $user['email'],
                'calendly_user_name' => $user['name'],
            ]);

            // Fetch and store event types
            $eventTypes = $this->calendlyService->getEventTypes($integration);
            $integration->update(['event_types' => $eventTypes]);

            // Create webhook subscription
            try {
                $webhookUrl = route('api.webhooks.calendly');
                $webhook = $this->calendlyService->createWebhookSubscription($integration, $webhookUrl);

                $integration->update([
                    'webhook_id' => $webhook['uri'],
                    'webhook_signing_key' => $webhook['signing_key'] ?? null,
                ]);
            } catch (\Exception $e) {
                // Webhook creation failed, but integration is still valid
                \Log::warning('Failed to create Calendly webhook', [
                    'integration_id' => $integration->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return redirect()->route('settings.calendly.index')
                ->with('success', __('Successfully connected to Calendly!'));

        } catch (\Exception $e) {
            \Log::error('Calendly OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('settings.calendly.index')
                ->with('error', __('Failed to connect to Calendly: ') . $e->getMessage());
        }
    }

    /**
     * Disconnect Calendly integration.
     */
    public function disconnect(Request $request, CalendlyIntegration $integration): RedirectResponse
    {
        // Verify ownership
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            // Delete webhook subscription
            $this->calendlyService->deleteWebhookSubscription($integration);

            // Revoke OAuth token
            if ($integration->access_token) {
                $this->oauthService->revokeToken($integration);
            }
        } catch (\Exception $e) {
            \Log::warning('Error during Calendly disconnect', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Delete the integration
        $integration->delete();

        return redirect()->route('settings.calendly.index')
            ->with('success', __('Calendly integration disconnected.'));
    }

    /**
     * Update integration settings.
     */
    public function updateSettings(Request $request, CalendlyIntegration $integration): RedirectResponse
    {
        // Verify ownership
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.crm.enabled' => 'boolean',
            'settings.crm.default_status' => 'nullable|string',
            'settings.crm.create_tasks' => 'boolean',
            'settings.crm.default_owner_id' => 'nullable|integer|exists:users,id',
            'settings.mailing_lists.enabled' => 'boolean',
            'settings.mailing_lists.default_list_ids' => 'nullable|array',
            'settings.mailing_lists.default_tag_ids' => 'nullable|array',
            'settings.event_type_mappings' => 'nullable|array',
            'settings.automation.trigger_on_booking' => 'boolean',
            'settings.automation.trigger_on_cancellation' => 'boolean',
            'settings.automation.trigger_on_no_show' => 'boolean',
        ]);

        $integration->update(['settings' => $validated['settings']]);

        return redirect()->back()->with('success', __('Settings updated successfully.'));
    }

    /**
     * Update API credentials.
     */
    public function updateCredentials(Request $request, CalendlyIntegration $integration): JsonResponse
    {
        // Verify ownership
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'client_id' => 'required|string|min:10',
            'client_secret' => 'required|string|min:10',
        ]);

        $integration->update([
            'client_id' => $validated['client_id'],
            'client_secret' => $validated['client_secret'],
        ]);

        return response()->json([
            'success' => true,
            'message' => __('API credentials updated successfully.'),
        ]);
    }

    /**
     * Sync event types from Calendly.
     */
    public function syncEventTypes(Request $request, CalendlyIntegration $integration): RedirectResponse
    {
        // Verify ownership
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $eventTypes = $this->calendlyService->getEventTypes($integration);
            $integration->update(['event_types' => $eventTypes]);

            return redirect()->back()->with('success', __('Event types synced successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to sync event types: ') . $e->getMessage());
        }
    }

    /**
     * Test webhook configuration.
     */
    public function testWebhook(Request $request, CalendlyIntegration $integration): RedirectResponse
    {
        // Verify ownership
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $webhook = $this->calendlyService->getWebhookSubscription($integration);

            if (!$webhook) {
                // Try to recreate webhook
                $webhookUrl = route('api.webhooks.calendly');
                $webhook = $this->calendlyService->createWebhookSubscription($integration, $webhookUrl);

                $integration->update([
                    'webhook_id' => $webhook['uri'],
                    'webhook_signing_key' => $webhook['signing_key'] ?? null,
                ]);

                return redirect()->back()->with('success', __('Webhook subscription recreated successfully.'));
            }

            return redirect()->back()->with('success', __('Webhook subscription is active.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Webhook test failed: ') . $e->getMessage());
        }
    }

    /**
     * Get recent events for an integration.
     */
    public function events(Request $request, CalendlyIntegration $integration): JsonResponse
    {
        // Verify ownership
        if ($integration->user_id !== $request->user()->id) {
            abort(403);
        }

        $events = CalendlyEvent::where('calendly_integration_id', $integration->id)
            ->with(['subscriber:id,email,first_name,last_name', 'crmContact:id,status', 'crmTask:id,status'])
            ->latest()
            ->paginate(20);

        return response()->json($events);
    }
}
