<?php

namespace App\Http\Controllers;

use App\Licensing\Feature;
use App\Models\DedicatedIpAddress;
use App\Models\DomainConfiguration;
use App\Models\Mailbox;
use App\Services\LicenseService;
use App\Services\Mail\BounceMailboxService;
use App\Services\Mail\BounceWebhookAuthenticator;
use App\Services\Mail\MailProviderService;
use App\Services\Mail\MailboxReputationService;
use App\Services\Mail\ReplyMailboxService;
use App\Services\Nmi\MtaCredentialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MailboxController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    public function __construct(
        private MailProviderService $providerService,
        private BounceMailboxService $bounceService,
        private MailboxReputationService $reputationService,
        private ReplyMailboxService $replyService,
        private MtaCredentialService $mtaCredentials,
        private LicenseService $license
    ) {}

    /**
     * Display list of mailboxes
     */
    public function index()
    {
        $mailboxes = Mailbox::forUser(Auth::id())
            ->with(['domainConfiguration:id,domain', 'dedicatedIp:id,ip_address'])
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
                    // NetSendo MTA (NMI): the domain the mailbox sends as and its dedicated IP
                    'domain_configuration_id' => $mailbox->domain_configuration_id,
                    'dedicated_ip_id' => $mailbox->dedicated_ip_id,
                    'nmi_domain' => $mailbox->domainConfiguration?->domain,
                    'nmi_ip' => $mailbox->dedicatedIp?->ip_address,
                    // Bounce mailbox monitoring
                    'bounce_enabled' => $mailbox->bounce_enabled,
                    'bounce_imap_host' => $mailbox->bounce_imap_host,
                    'bounce_imap_port' => $mailbox->bounce_imap_port,
                    'bounce_imap_encryption' => $mailbox->bounce_imap_encryption,
                    'bounce_imap_folder' => $mailbox->bounce_imap_folder,
                    'bounce_last_scanned_at' => $mailbox->bounce_last_scanned_at?->toIso8601String(),
                    'bounce_last_scan_count' => $mailbox->bounce_last_scan_count,
                    // Bounce webhooks (URLs and which secrets are set)
                    'bounce_webhook' => $this->bounceWebhookSettings($mailbox),
                    // Reply inbox monitoring
                    'reply_enabled' => $mailbox->reply_enabled,
                    'reply_imap_host' => $mailbox->reply_imap_host,
                    'reply_imap_port' => $mailbox->reply_imap_port,
                    'reply_imap_encryption' => $mailbox->reply_imap_encryption,
                    'reply_imap_folder' => $mailbox->reply_imap_folder,
                    'reply_imap_username' => $mailbox->getDecryptedReplyCredentials()['username'] ?? null,
                    'reply_last_scanned_at' => $mailbox->reply_last_scanned_at?->toIso8601String(),
                    'reply_last_scan_count' => $mailbox->reply_last_scan_count,
                    // Custom headers
                    'custom_headers' => $mailbox->custom_headers ?? [],
                    'tracking_enabled' => $mailbox->tracking_enabled ?? true,
                    // Reputation monitoring
                    'reputation_overall' => $mailbox->reputation_overall ?? 'unchecked',
                    'reputation_checked_at' => $mailbox->reputation_checked_at?->toIso8601String(),
                    'reputation_status' => $mailbox->reputation_status,
                ];
            });

        return Inertia::render('Settings/Mailboxes/Index', [
            'mailboxes' => $mailboxes,
            'providers' => Mailbox::getPanelProviders(),
            'gmail_configured' => \App\Models\GoogleIntegration::where('user_id', Auth::id())->where('status', 'active')->exists() || \App\Models\Setting::where('key', 'google_client_id')->exists() || !empty(config('services.google.client_id')),
            'providerFields' => [
                Mailbox::PROVIDER_SMTP => MailProviderService::getProviderFields(Mailbox::PROVIDER_SMTP),
                Mailbox::PROVIDER_SENDGRID => MailProviderService::getProviderFields(Mailbox::PROVIDER_SENDGRID),
                Mailbox::PROVIDER_GMAIL => MailProviderService::getProviderFields(Mailbox::PROVIDER_GMAIL),
                Mailbox::PROVIDER_NMI => MailProviderService::getProviderFields(Mailbox::PROVIDER_NMI),
            ],
            'nmi' => $this->nmiFormOptions(),
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
            'provider' => ['required', Rule::in(Mailbox::panelProviders())],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'allowed_types' => ['required', 'array', 'min:1'],
            'allowed_types.*' => [Rule::in([Mailbox::TYPE_BROADCAST, Mailbox::TYPE_AUTORESPONDER, Mailbox::TYPE_SYSTEM])],
            // Gmail logs in with OAuth, the NetSendo MTA with the login of the chosen domain
            'credentials' => [Rule::requiredIf(fn () => !in_array($request->provider, [Mailbox::PROVIDER_GMAIL, Mailbox::PROVIDER_NMI], true)), 'array'],
            'domain_configuration_id' => [Rule::requiredIf(fn () => $request->provider === Mailbox::PROVIDER_NMI), 'nullable', 'integer'],
            'dedicated_ip_id' => ['nullable', 'integer'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'time_restriction' => ['nullable', 'integer', 'min:0'],
            'google_integration_id' => ['nullable', 'exists:google_integrations,id'],
            'tracking_enabled' => ['nullable', 'boolean'],
            'custom_headers' => ['nullable', 'array'],
            'custom_headers.*.key' => ['required_with:custom_headers', 'string', 'max:255'],
            'custom_headers.*.value' => ['required_with:custom_headers', 'string', 'max:1024'],
        ], [], $this->fieldNames());

        $nmi = $this->resolveNmiLink($validated);

        $mailbox = Mailbox::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'provider' => $validated['provider'],
            'from_email' => $validated['from_email'],
            'from_name' => $validated['from_name'],
            'reply_to' => $validated['reply_to'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'allowed_types' => array_values($validated['allowed_types']),
            'credentials' => $nmi ? [] : ($validated['credentials'] ?? []),
            'daily_limit' => $validated['daily_limit'] ?? null,
            'time_restriction' => $validated['time_restriction'] ?? null,
            'google_integration_id' => $validated['google_integration_id'] ?? null,
            'domain_configuration_id' => ($nmi['domain'] ?? null)?->id,
            'dedicated_ip_id' => ($nmi['ip'] ?? null)?->id,
            'custom_headers' => $validated['custom_headers'] ?? null,
            'tracking_enabled' => $validated['tracking_enabled'] ?? true,
        ]);

        $this->claimDedicatedIp($nmi);

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

        if ($request->provider === Mailbox::PROVIDER_GMAIL && empty($request->from_email)) {
             $defaultEmail = 'pending-auth@gmail.com';

             if (!empty($request->google_integration_id)) {
                 $integration = \App\Models\GoogleIntegration::find($request->google_integration_id);
                 if ($integration && filter_var($integration->name, FILTER_VALIDATE_EMAIL)) {
                     $defaultEmail = $integration->name;
                 }
             } else {
                 // Try to keep existing email if we are just updating unrelated fields
                 // But frontend specifically clears it, so we need a value.
                 // If we have an existing connected email, use that?
                 // The store logic uses pending-auth, so let's stick to that or the integration name.
                 // Actually, if we are editing, we might already have a google_integration_id on the mailbox
                 // if the request didn't send one (nullable).
                 // But the request validation rule says 'google_integration_id' is nullable.

                 // Let's stick to the store logic exactly for consistency first.
                 // Use integration from request if present, otherwise default.
             }

             // IMPROVEMENT over store: If we have an existing email on the mailbox and we are just updating settings,
             // maybe we should preserve it if integration hasn't changed?
             // But the frontend clears it to avoid browser autofill of "admin@example.com" into that field.
             // If we set it to "pending-auth", it might overwrite the actual email if we don't be careful.
             // However, the "gmail_email" is stored in credentials, not from_email column primarily?
             // unique constraint? mailboxes table has from_email.

             // Wait, in `store`, we set `from_email`.
             // In `update`, we update `from_email`.
             // If I set it to `pending-auth@gmail.com` here, and I already had `myname@gmail.com`, I will LOSE the email address display in the UI list!
             // The `store` method is creating NEW, so `pending-auth` is fine until connected.
             // The `update` logic needs to be smarter:
             // 1. If request has google_integration_id, try to use that email.
             // 2. If NOT, and we already have a from_email in the DB for this mailbox, RETAIN IT?
             //    But the request `from_email` is empty. The validation `required` fails.
             //    So we must merge a value into the request before validation.

             // Let's check if we can fallback to `$mailbox->from_email` if it's already set and valid?

             if (!empty($request->google_integration_id)) {
                 $integration = \App\Models\GoogleIntegration::find($request->google_integration_id);
                 if ($integration && filter_var($integration->name, FILTER_VALIDATE_EMAIL)) {
                     $defaultEmail = $integration->name;
                 }
             } elseif (!empty($mailbox->from_email)) {
                  // Fallback to existing email if no new integration is being set, to avoid overwriting with "pending-auth"
                  $defaultEmail = $mailbox->from_email;
             }

             $request->merge(['from_email' => $defaultEmail]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // The mailbox's own provider stays valid: an NMI mailbox remains editable on
            // an installation where the NetSendo MTA has been switched off since
            'provider' => ['required', Rule::in(array_unique([...Mailbox::panelProviders(), $mailbox->provider]))],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'allowed_types' => ['required', 'array', 'min:1'],
            'allowed_types.*' => [Rule::in([Mailbox::TYPE_BROADCAST, Mailbox::TYPE_AUTORESPONDER, Mailbox::TYPE_SYSTEM])],
            'credentials' => ['sometimes', 'array'],
            'domain_configuration_id' => ['sometimes', 'nullable', 'integer'],
            'dedicated_ip_id' => ['sometimes', 'nullable', 'integer'],
            'daily_limit' => ['nullable', 'integer', 'min:1'],
            'time_restriction' => ['nullable', 'integer', 'min:0'],
            'google_integration_id' => ['nullable', 'exists:google_integrations,id'],
            'tracking_enabled' => ['nullable', 'boolean'],
            'custom_headers' => ['nullable', 'array'],
            'custom_headers.*.key' => ['required_with:custom_headers', 'string', 'max:255'],
            'custom_headers.*.value' => ['required_with:custom_headers', 'string', 'max:1024'],
        ], [], $this->fieldNames());

        $webhookValidated = $request->validate([
            'bounce_webhook_sendgrid_key' => [
                'nullable', 'string', 'max:2000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (is_string($value) && trim($value) !== ''
                        && BounceWebhookAuthenticator::normaliseSendGridKey($value) === null) {
                        $fail(__('mailboxes.bounce_webhooks.invalid_sendgrid_key'));
                    }
                },
            ],
            'bounce_webhook_mailgun_key' => ['nullable', 'string', 'max:255'],
            'bounce_webhook_remove' => ['nullable', 'array'],
            'bounce_webhook_remove.*' => [Rule::in(['sendgrid', 'mailgun'])],
        ]);

        $replyValidated = $request->has('reply_enabled') ? $request->validate([
            'reply_enabled' => ['boolean'],
            'reply_imap_host' => ['nullable', 'required_if:reply_enabled,true', 'string', 'max:255'],
            'reply_imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'reply_imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'none'])],
            'reply_imap_folder' => ['nullable', 'string', 'max:100'],
            'reply_imap_username' => ['nullable', 'string', 'max:255'],
            'reply_imap_password' => ['nullable', 'string', 'max:1024'],
        ], [], $this->fieldNames()) : null;

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
            'custom_headers' => $validated['custom_headers'] ?? null,
            'tracking_enabled' => $validated['tracking_enabled'] ?? $mailbox->tracking_enabled ?? true,
        ];

        // NetSendo MTA: the domain (and dedicated IP) the mailbox sends through. Any other
        // provider has neither, so switching away drops the link.
        $nmi = $this->resolveNmiLink($validated, $mailbox);
        if (! $nmi || $nmi['domain']) {
            $updateData['domain_configuration_id'] = ($nmi['domain'] ?? null)?->id;
            $updateData['dedicated_ip_id'] = ($nmi['ip'] ?? null)?->id;
        }

        if ($nmi) {
            // A login typed in for another provider is of no use to the NetSendo MTA
            $updateData['credentials'] = [];
        }

        // Only update credentials if provided
        // Handle credentials update with merging
        if (!$nmi && !empty($validated['credentials'])) {
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

        $this->claimDedicatedIp($nmi);

        // Handle bounce mailbox configuration separately
        if ($request->has('bounce_enabled')) {
            $bounceData = [
                'bounce_enabled' => $request->boolean('bounce_enabled'),
                'bounce_imap_host' => $request->input('bounce_imap_host'),
                'bounce_imap_port' => $request->input('bounce_imap_port', 993),
                'bounce_imap_encryption' => $request->input('bounce_imap_encryption', 'ssl'),
                'bounce_imap_folder' => $request->input('bounce_imap_folder', 'INBOX'),
            ];

            // Handle bounce IMAP credentials (merge with existing if password not provided)
            $bounceUsername = $request->input('bounce_imap_username');
            $bouncePassword = $request->input('bounce_imap_password');

            if ($bounceUsername || $bouncePassword) {
                $currentBounceCreds = $mailbox->getDecryptedBounceCredentials();
                $bounceData['bounce_imap_credentials'] = [
                    'username' => $bounceUsername ?: ($currentBounceCreds['username'] ?? ''),
                    'password' => $bouncePassword ?: ($currentBounceCreds['password'] ?? ''),
                ];
            }

            $mailbox->update($bounceData);
        }

        // Bounce webhook secrets: an empty field keeps the stored key
        $webhookChanges = [];
        foreach ($webhookValidated['bounce_webhook_remove'] ?? [] as $provider) {
            $webhookChanges[$provider === 'sendgrid' ? 'sendgrid_verification_key' : 'mailgun_signing_key'] = null;
        }
        if (filled($webhookValidated['bounce_webhook_sendgrid_key'] ?? null)) {
            $webhookChanges['sendgrid_verification_key'] = BounceWebhookAuthenticator::normaliseSendGridKey(
                $webhookValidated['bounce_webhook_sendgrid_key']
            );
        }
        if (filled($webhookValidated['bounce_webhook_mailgun_key'] ?? null)) {
            $webhookChanges['mailgun_signing_key'] = trim($webhookValidated['bounce_webhook_mailgun_key']);
        }
        if ($webhookChanges !== []) {
            $mailbox->updateBounceWebhookCredentials($webhookChanges);
        }

        // Handle reply inbox configuration separately
        if ($replyValidated !== null) {
            $replyData = [
                'reply_enabled' => $request->boolean('reply_enabled'),
                'reply_imap_host' => $replyValidated['reply_imap_host'] ?? null,
                'reply_imap_port' => $replyValidated['reply_imap_port'] ?? 993,
                'reply_imap_encryption' => $replyValidated['reply_imap_encryption'] ?? 'ssl',
                'reply_imap_folder' => $replyValidated['reply_imap_folder'] ?? 'INBOX',
            ];

            // Another server or folder has other UIDs: start over from recent messages
            if ($replyData['reply_imap_host'] !== $mailbox->reply_imap_host
                || $replyData['reply_imap_folder'] !== $mailbox->reply_imap_folder) {
                $replyData['reply_last_uid'] = null;
                $replyData['reply_uid_validity'] = null;
            }

            // Handle reply IMAP credentials (merge with existing if password not provided)
            $replyUsername = $replyValidated['reply_imap_username'] ?? null;
            $replyPassword = $replyValidated['reply_imap_password'] ?? null;

            if ($replyUsername || $replyPassword) {
                $currentReplyCreds = $mailbox->getDecryptedReplyCredentials();
                $replyData['reply_imap_credentials'] = [
                    'username' => $replyUsername ?: ($currentReplyCreds['username'] ?? ''),
                    'password' => $replyPassword ?: ($currentReplyCreds['password'] ?? ''),
                ];

                if ($replyData['reply_imap_credentials']['username'] !== ($currentReplyCreds['username'] ?? '')) {
                    $replyData['reply_last_uid'] = null;
                    $replyData['reply_uid_validity'] = null;
                }
            }

            $mailbox->update($replyData);
        }

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

    /**
     * Generate (or replace) the token that authenticates the Postmark and
     * generic bounce webhooks of this mailbox. The previous token stops working.
     */
    public function regenerateBounceWebhookToken(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        $mailbox->updateBounceWebhookCredentials(['token' => BounceWebhookAuthenticator::generateToken()]);

        return response()->json([
            'success' => true,
            'message' => __('mailboxes.bounce_webhooks.token_generated'),
            'bounce_webhook' => $this->bounceWebhookSettings($mailbox->fresh()),
        ]);
    }

    /**
     * What the mailbox form needs to offer the "NetSendo MTA" provider: the account's
     * verified domains and its dedicated IPs. Empty lists where the MTA is switched off.
     */
    private function nmiFormOptions(): array
    {
        if (! Mailbox::nmiAvailable()) {
            return ['enabled' => false, 'domains' => [], 'ips' => []];
        }

        $userId = Auth::id();

        return [
            'enabled' => true,
            'domains' => DomainConfiguration::forUser($userId)
                ->verified()
                ->orderBy('domain')
                ->get(['id', 'domain'])
                ->map(fn (DomainConfiguration $domain) => [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                ])
                ->values(),
            'ips' => DedicatedIpAddress::query()
                ->whereHas('pool', fn ($pool) => $pool->where('user_id', $userId))
                ->active()
                ->orderBy('ip_address')
                ->get(['id', 'ip_address', 'hostname', 'domain_configuration_id', 'warming_status'])
                ->map(fn (DedicatedIpAddress $ip) => [
                    'id' => $ip->id,
                    'ip_address' => $ip->ip_address,
                    'hostname' => $ip->hostname,
                    // An IP signs with the DKIM key of one domain: null = free to take
                    'domain_configuration_id' => $ip->domain_configuration_id,
                    'warming_status' => $ip->warming_status,
                ])
                ->values(),
        ];
    }

    /**
     * The verified domain and the dedicated IP an NMI mailbox is saved with, checked
     * against the account; null for every other provider.
     *
     * Setting NMI up needs GOLD (Feature::MailInfrastructure): creating an NMI mailbox,
     * switching a mailbox to NMI, changing its domain or IP. Saving anything else on an
     * NMI mailbox does not, in line with the settings/nmi routes: what was set up under
     * GOLD keeps working and stays editable.
     *
     * The domain's login to the MTA is issued here, before the mailbox is saved, so a
     * mailbox that cannot log in is never stored.
     *
     * @return array{domain: ?DomainConfiguration, ip: ?DedicatedIpAddress}|null
     */
    private function resolveNmiLink(array $validated, ?Mailbox $mailbox = null): ?array
    {
        if ($validated['provider'] !== Mailbox::PROVIDER_NMI) {
            return null;
        }

        $wasNmi = $mailbox?->isNmi() ?? false;

        // A partial update (the active switch in the list) carries no domain: keep the link
        $partial = $wasNmi && ! array_key_exists('domain_configuration_id', $validated);

        $domainId = $partial
            ? $mailbox->domain_configuration_id
            : ($validated['domain_configuration_id'] ?? null);
        $ipId = array_key_exists('dedicated_ip_id', $validated)
            ? $validated['dedicated_ip_id']
            : ($partial ? $mailbox->dedicated_ip_id : null);

        $changed = ! $wasNmi
            || (int) $domainId !== (int) $mailbox->domain_configuration_id
            || (int) $ipId !== (int) $mailbox->dedicated_ip_id;

        if ($changed) {
            $this->license->ensureAllows(Feature::MailInfrastructure);
        }

        $domain = $domainId
            ? DomainConfiguration::forUser(Auth::id())->find($domainId)
            : null;

        if (! $domain) {
            // A mailbox whose domain was removed cannot send, but the list must still be
            // able to switch it off: nothing to check, and the stored link is left alone
            if ($partial) {
                return ['domain' => null, 'ip' => null];
            }

            throw ValidationException::withMessages([
                'domain_configuration_id' => __('mailboxes.nmi.errors.domain_required'),
            ]);
        }

        $fromDomain = strtolower((string) substr(strrchr($validated['from_email'], '@') ?: '', 1));
        if ($fromDomain !== strtolower($domain->domain)) {
            throw ValidationException::withMessages([
                'from_email' => __('mailboxes.nmi.errors.from_domain_mismatch', ['domain' => $domain->domain]),
            ]);
        }

        $ip = $ipId
            ? DedicatedIpAddress::query()
                ->whereHas('pool', fn ($pool) => $pool->where('user_id', Auth::id()))
                ->find($ipId)
            : null;

        // The link the mailbox already has is not judged again: a domain that lost its
        // verification or an IP that was paused must not stop the mailbox from being
        // renamed or switched off
        if (! $changed) {
            return ['domain' => $domain, 'ip' => $ip];
        }

        if (! $domain->cname_verified) {
            throw ValidationException::withMessages([
                'domain_configuration_id' => __('mailboxes.nmi.errors.domain_not_verified', ['domain' => $domain->domain]),
            ]);
        }

        if ($ipId) {
            if (! $ip || ! $ip->is_active) {
                throw ValidationException::withMessages([
                    'dedicated_ip_id' => __('mailboxes.nmi.errors.ip_invalid'),
                ]);
            }

            if ($ip->domain_configuration_id !== null && (int) $ip->domain_configuration_id !== (int) $domain->id) {
                throw ValidationException::withMessages([
                    'dedicated_ip_id' => __('mailboxes.nmi.errors.ip_other_domain', ['ip' => $ip->ip_address]),
                ]);
            }
        }

        try {
            $this->mtaCredentials->ensureFor($domain);
        } catch (\RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages([
                'domain_configuration_id' => __('mailboxes.nmi.errors.mta_login_file', ['file' => $this->mtaCredentials->authFile()]),
            ]);
        }

        return ['domain' => $domain, 'ip' => $ip];
    }

    /**
     * A dedicated IP belongs to the domain of the first mailbox that sends through it:
     * its DKIM key is generated for, and published under, that domain (DkimKeyManager).
     */
    private function claimDedicatedIp(?array $nmi): void
    {
        if ($nmi && $nmi['domain'] && $nmi['ip'] && $nmi['ip']->domain_configuration_id === null) {
            $nmi['ip']->update(['domain_configuration_id' => $nmi['domain']->id]);
        }
    }

    /**
     * Readable field names for the form's validation messages ("credentials" alone means
     * nothing to the person filling in the form).
     */
    private function fieldNames(): array
    {
        $names = __('mailboxes.attributes');

        return is_array($names) ? $names : [];
    }

    /**
     * Bounce webhook URLs of a mailbox and which secrets are set. The token is
     * shown to the owner so it can be copied into Postmark or a custom sender;
     * the Mailgun signing key is never sent back.
     */
    private function bounceWebhookSettings(Mailbox $mailbox): array
    {
        $credentials = $mailbox->getDecryptedBounceWebhookCredentials();

        $urls = [];
        foreach (Mailbox::BOUNCE_WEBHOOK_PROVIDERS as $provider) {
            $urls[$provider] = route("webhooks.bounce.{$provider}", ['mailbox' => $mailbox->id]);
        }

        return [
            'urls' => $urls,
            'token' => $credentials['token'] ?? null,
            'sendgrid_key_set' => !empty($credentials['sendgrid_verification_key']),
            'mailgun_key_set' => !empty($credentials['mailgun_signing_key']),
        ];
    }

    /**
     * Test bounce IMAP connection
     */
    public function testBounce(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        if (!$mailbox->bounce_enabled || !$mailbox->bounce_imap_host) {
            return response()->json([
                'success' => false,
                'message' => __('mailboxes.bounce.test_failed') . ': Bounce monitoring not configured.',
            ]);
        }

        $result = $this->bounceService->testConnection($mailbox);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('mailboxes.bounce.test_success')
                : __('mailboxes.bounce.test_failed') . ': ' . $result['message'],
            'folder_count' => $result['folder_count'] ?? null,
        ]);
    }

    /**
     * Test reply inbox IMAP connection
     */
    public function testReply(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        if (!$mailbox->reply_enabled || !$mailbox->reply_imap_host) {
            return response()->json([
                'success' => false,
                'message' => __('mailboxes.replies.test_failed') . ': ' . __('mailboxes.replies.not_configured'),
            ]);
        }

        $result = $this->replyService->testConnection($mailbox);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? __('mailboxes.replies.test_success')
                : __('mailboxes.replies.test_failed') . ': ' . $result['message'],
            'folder_count' => $result['folder_count'] ?? null,
        ]);
    }

    /**
     * Check mailbox domain reputation (blacklist check)
     */
    public function checkReputation(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);

        try {
            $results = $this->reputationService->checkAndUpdateMailbox($mailbox);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $this->reputationService->getSummary($mailbox->fresh()),
                    'details' => $this->reputationService->getDetails($mailbox->fresh()),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
