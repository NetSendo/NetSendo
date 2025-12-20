<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Services\Mail\MailProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MailboxController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    public function __construct(
        private MailProviderService $providerService
    ) {}

    /**
     * Display list of mailboxes
     */
    public function index()
    {
        $mailboxes = Mailbox::forUser(Auth::id())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function ($mailbox) {
                $credentials = $mailbox->getDecryptedCredentials();

                return [
                    'id' => $mailbox->id,
                    'name' => $mailbox->name,
                    'provider' => $mailbox->provider,
                    'from_email' => $mailbox->from_email,
                    'from_name' => $mailbox->from_name,
                    'is_default' => $mailbox->is_default,
                    'is_active' => $mailbox->is_active,
                    'allowed_types' => $mailbox->allowed_types,
                    'daily_limit' => $mailbox->daily_limit,
                    'sent_today' => $mailbox->sent_today,
                    'last_tested_at' => $mailbox->last_tested_at?->toIso8601String(),
                    'last_test_success' => $mailbox->last_test_success,
                    'last_test_message' => $mailbox->last_test_message,
                    'credentials' => [
                        'host' => $credentials['host'] ?? null,
                        'port' => $credentials['port'] ?? null,
                        'username' => $credentials['username'] ?? null,
                        'encryption' => $credentials['encryption'] ?? null,
                        // Don't send password
                    ],
                    'gmail_connected' => !empty($credentials['access_token']),
                    'gmail_email' => $credentials['gmail_email'] ?? null,
                    'reply_to' => $mailbox->reply_to,
                    'time_restriction' => $mailbox->time_restriction,
                    'google_integration_id' => $mailbox->google_integration_id,
                ];
            });

        return Inertia::render('Settings/Mailboxes/Index', [
            'mailboxes' => $mailboxes,
            'providers' => Mailbox::getProviders(),
            'gmail_configured' => \App\Models\GoogleIntegration::where('user_id', Auth::id())->where('status', 'active')->exists() || \App\Models\Setting::where('key', 'google_client_id')->exists() || !empty(config('services.google.client_id')),
            'providerFields' => [
                Mailbox::PROVIDER_SMTP => MailProviderService::getProviderFields(Mailbox::PROVIDER_SMTP),
                Mailbox::PROVIDER_SENDGRID => MailProviderService::getProviderFields(Mailbox::PROVIDER_SENDGRID),
                Mailbox::PROVIDER_GMAIL => MailProviderService::getProviderFields(Mailbox::PROVIDER_GMAIL),
            ],
            'messageTypes' => [
                Mailbox::TYPE_BROADCAST => __('mailboxes.types.broadcast'),
                Mailbox::TYPE_AUTORESPONDER => __('mailboxes.types.autoresponder'),
                Mailbox::TYPE_SYSTEM => __('mailboxes.types.system'),
            ],
            'google_integrations' => \App\Models\GoogleIntegration::where('user_id', Auth::id())->where('status', 'active')->get(['id', 'name', 'client_id']),
        ]);
    }

    /**
     * Store a new mailbox
     */
    public function store(Request $request)
    {
        if ($request->provider === Mailbox::PROVIDER_GMAIL && empty($request->from_email)) {
             $defaultEmail = 'pending-auth@gmail.com';
             
             if (!empty($request->google_integration_id)) {
                 $integration = \App\Models\GoogleIntegration::find($request->google_integration_id);
                 if ($integration && filter_var($integration->name, FILTER_VALIDATE_EMAIL)) {
                     $defaultEmail = $integration->name;
                 }
             }

            $request->merge(['from_email' => $defaultEmail]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::in([Mailbox::PROVIDER_SMTP, Mailbox::PROVIDER_SENDGRID, Mailbox::PROVIDER_GMAIL])],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'allowed_types' => ['required', 'array', 'min:1'],
            'allowed_types.*' => [Rule::in([Mailbox::TYPE_BROADCAST, Mailbox::TYPE_AUTORESPONDER, Mailbox::TYPE_SYSTEM])],
            'credentials' => [Rule::requiredIf(fn () => $request->provider !== Mailbox::PROVIDER_GMAIL), 'array'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'time_restriction' => ['nullable', 'integer', 'min:0'],
            'google_integration_id' => ['nullable', 'exists:google_integrations,id'],
        ]);

        $mailbox = Mailbox::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'from_email' => $validated['from_email'],
            'from_name' => $validated['from_name'],
            'reply_to' => $validated['reply_to'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'allowed_types' => array_values($validated['allowed_types']),
            'credentials' => $validated['credentials'],
            'daily_limit' => $validated['daily_limit'] ?? null,
            'time_restriction' => $validated['time_restriction'] ?? null,
            'google_integration_id' => $validated['google_integration_id'] ?? null,
        ]);

        // Set as default if it's the first mailbox
        if (Mailbox::forUser(Auth::id())->count() === 1) {
            $mailbox->setAsDefault();
        }

        return back()->with('success', __('mailboxes.created'));
    }

    /**
     * Update a mailbox
     */
    public function update(Request $request, Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::in([Mailbox::PROVIDER_SMTP, Mailbox::PROVIDER_SENDGRID, Mailbox::PROVIDER_GMAIL])],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'allowed_types' => ['required', 'array', 'min:1'],
            'allowed_types.*' => [Rule::in([Mailbox::TYPE_BROADCAST, Mailbox::TYPE_AUTORESPONDER, Mailbox::TYPE_SYSTEM])],
            'credentials' => ['sometimes', 'array'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'time_restriction' => ['nullable', 'integer', 'min:0'],
            'google_integration_id' => ['nullable', 'exists:google_integrations,id'],
        ]);

        $updateData = [
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'from_email' => $validated['from_email'],
            'from_name' => $validated['from_name'],
            'reply_to' => $validated['reply_to'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'allowed_types' => array_values($validated['allowed_types']),
            'daily_limit' => $validated['daily_limit'],
            'time_restriction' => $validated['time_restriction'] ?? null,
            'google_integration_id' => $validated['google_integration_id'] ?? null,
        ];

        // Only update credentials if provided
        // Handle credentials update with merging
        if (!empty($validated['credentials'])) {
            $currentCredentials = $mailbox->getDecryptedCredentials();
            $newCredentials = $validated['credentials'];

            // For SMTP: if password is not provided in update (empty), keep the old one
            if ($mailbox->provider === Mailbox::PROVIDER_SMTP) {
                if (empty($newCredentials['password']) && !empty($currentCredentials['password'])) {
                    $newCredentials['password'] = $currentCredentials['password'];
                }
            }
            
            // For SendGrid: same logic for api_key if we were masking it, but we usually treat it as password
            // If the user sends empty api_key, we probably should keep old one?
            if ($mailbox->provider === Mailbox::PROVIDER_SENDGRID) {
                 if (empty($newCredentials['api_key']) && !empty($currentCredentials['api_key'])) {
                    $newCredentials['api_key'] = $currentCredentials['api_key'];
                }
            }

            $updateData['credentials'] = array_merge($currentCredentials, $newCredentials);
        }

        $mailbox->update($updateData);

        return back()->with('success', __('mailboxes.updated'));
    }

    /**
     * Delete a mailbox
     */
    public function destroy(Mailbox $mailbox)
    {
        $this->authorize('delete', $mailbox);

        $wasDefault = $mailbox->is_default;
        $mailbox->delete();

        // If this was the default, set another one as default
        if ($wasDefault) {
            $newDefault = Mailbox::forUser(Auth::id())->active()->first();
            if ($newDefault) {
                $newDefault->setAsDefault();
            }
        }

        return back()->with('success', __('mailboxes.deleted'));
    }

    /**
     * Test mailbox connection
     */
    public function test(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        try {
            $provider = $this->providerService->getProvider($mailbox);
            $testEmail = Auth::user()->email;
            $result = $provider->testConnection($testEmail);

            $mailbox->updateTestResult($result['success'], $result['message']);

            return response()->json($result);
        } catch (\Exception $e) {
            $mailbox->updateTestResult(false, $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Set mailbox as default
     */
    public function setDefault(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        $mailbox->setAsDefault();

        return back()->with('success', __('mailboxes.set_default'));
    }
}
