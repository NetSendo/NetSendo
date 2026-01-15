<?php

namespace App\Services\Forms;

use App\Models\SubscriptionForm;
use App\Models\FormSubmission;
use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\CustomField;
use App\Models\SuppressionList;
use App\Events\SubscriberSignedUp;
use App\Events\FormSubmitted;
use App\Services\AffiliateConversionService;
use App\Services\WebhookDispatcher;
use App\Services\SystemEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FormSubmissionService
{
    protected WebhookDispatcher $webhookDispatcher;
    protected SystemEmailService $emailService;
    protected AffiliateConversionService $affiliateConversionService;

    public function __construct(
        WebhookDispatcher $webhookDispatcher,
        SystemEmailService $emailService,
        AffiliateConversionService $affiliateConversionService
    ) {
        $this->webhookDispatcher = $webhookDispatcher;
        $this->emailService = $emailService;
        $this->affiliateConversionService = $affiliateConversionService;
    }

    /**
     * Process a form submission
     */
    public function processSubmission(SubscriptionForm $form, array $data, Request $request): FormSubmission
    {
        // Create submission record
        $submission = FormSubmission::create([
            'subscription_form_id' => $form->id,
            'submission_data' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'source' => $data['_source'] ?? 'form',
            'status' => 'pending',
        ]);

        try {
            DB::beginTransaction();

            // Validate submission
            $errors = $this->validateSubmission($form, $data);
            if (!empty($errors)) {
                $submission->markError(implode(', ', $errors));
                DB::commit();
                return $submission;
            }

            // Check honeypot
            if ($form->honeypot_enabled && !$this->checkHoneypot($data)) {
                $submission->markRejected('Spam detected (honeypot)');
                DB::commit();
                return $submission;
            }

            // Verify CAPTCHA
            if ($form->captcha_enabled && !empty($data['captcha_token'])) {
                if (!$this->verifyCaptcha($form, $data['captcha_token'])) {
                    $submission->markError('CAPTCHA verification failed');
                    DB::commit();
                    return $submission;
                }
            }

            // Create or update subscriber
            $result = $this->createOrUpdateSubscriber($data, $form->contactList, $form);
            $subscriber = $result['subscriber'];
            $isNewSubscriber = $result['isNew'];
            $wasAlreadySubscribed = $result['wasSubscribed'];
            $previousStatus = $result['previousStatus'] ?? null;

            // Link submission to subscriber
            $submission->update(['subscriber_id' => $subscriber->id]);

            // Link visitor device to subscriber (for pixel tracking)
            $visitorToken = $data['visitor_token'] ?? $request->cookie('ns_visitor') ?? null;
            if ($visitorToken) {
                $this->linkVisitorToSubscriber($visitorToken, $subscriber, $form->contactList->user_id);
            }

            // Handle co-registration
            if (!empty($form->coregister_lists)) {
                $this->handleCoregistration($subscriber, $form->coregister_lists);
            }

            // Mark as confirmed (or pending if double opt-in)
            if ($form->shouldUseDoubleOptin()) {
                $submission->update(['status' => 'pending']);
                // Send double opt-in confirmation email
                $this->emailService->sendSignupConfirmation($subscriber, $form->contactList);
            } else {
                $submission->markConfirmed();

                // Send re-subscribe notifications if applicable (only for non-double-opt-in)
                if ($wasAlreadySubscribed && $previousStatus === 'active') {
                    // User was already active, notify them
                    $this->emailService->sendAlreadyActiveNotification($subscriber, $form->contactList);
                } elseif ($wasAlreadySubscribed && $previousStatus !== 'active') {
                    // User was inactive/unsubscribed, send welcome back
                    $this->emailService->sendInactiveResubscribeNotification($subscriber, $form->contactList);
                } else {
                    // New subscriber without double opt-in - send welcome email
                    $this->emailService->sendSubscriptionWelcome($subscriber, $form->contactList);
                }
            }

            // Update form stats
            $form->incrementSubmissions();

            DB::commit();

            // Trigger integrations (outside transaction)
            $this->triggerIntegrations($submission);

            // Dispatch events for automations
            event(new SubscriberSignedUp($subscriber, $form->contactList, $form, 'form'));
            event(new FormSubmitted($submission, $subscriber, $form));

            // Prepare subscriber data for webhooks
            $subscriberData = [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'phone' => $subscriber->phone,
                'source' => 'form:' . $form->slug,
            ];

            // Dispatch subscriber.created webhook (for new subscribers)
            if ($isNewSubscriber) {
                $this->webhookDispatcher->dispatch($form->contactList->user_id, 'subscriber.created', [
                    'subscriber' => $subscriberData,
                    'list_id' => $form->contactList->id,
                    'list_name' => $form->contactList->name,
                    'form_id' => $form->id,
                    'form_name' => $form->name,
                ]);
            }

            // Dispatch subscriber.subscribed webhook (for new subscriptions to list)
            if (!$wasAlreadySubscribed) {
                $this->webhookDispatcher->dispatch($form->contactList->user_id, 'subscriber.subscribed', [
                    'subscriber' => $subscriberData,
                    'list_id' => $form->contactList->id,
                    'list_name' => $form->contactList->name,
                    'form_id' => $form->id,
                    'form_name' => $form->name,
                ]);
            } elseif ($wasAlreadySubscribed && $previousStatus !== 'active') {
                // Dispatch subscriber.resubscribed webhook (for re-activation after unsubscribe/inactive)
                $this->webhookDispatcher->dispatch($form->contactList->user_id, 'subscriber.resubscribed', [
                    'subscriber' => $subscriberData,
                    'list_id' => $form->contactList->id,
                    'list_name' => $form->contactList->name,
                    'form_id' => $form->id,
                    'form_name' => $form->name,
                    'previous_status' => $previousStatus,
                ]);
            } elseif ($wasAlreadySubscribed && $previousStatus === 'active') {
                // Dispatch subscriber.updated webhook (for already active subscribers updating their data)
                $this->webhookDispatcher->dispatch($form->contactList->user_id, 'subscriber.updated', [
                    'subscriber' => $subscriberData,
                    'list_id' => $form->contactList->id,
                    'list_name' => $form->contactList->name,
                    'form_id' => $form->id,
                    'form_name' => $form->name,
                ]);
            }

            // Trigger list webhook for subscribe event (legacy/list-specific webhook)
            $form->contactList->triggerWebhook('subscribe', [
                'subscriber_email' => $subscriber->email,
                'subscriber_id' => $subscriber->id,
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'phone' => $subscriber->phone,
                'source' => 'form:' . $form->slug,
                'form_id' => $form->id,
                'form_name' => $form->name,
            ]);

            // Track affiliate lead conversion (if applicable)
            try {
                $this->affiliateConversionService->recordLeadFromRequest(
                    $request,
                    $form->contactList->user_id,
                    $subscriber->email,
                    [
                        'form_id' => $form->id,
                        'form_slug' => $form->slug,
                        'subscriber_id' => $subscriber->id,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Affiliate lead tracking failed', [
                    'error' => $e->getMessage(),
                    'form_id' => $form->id,
                ]);
            }

            return $submission;


        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Form submission error', [
                'form_id' => $form->id,
                'error' => $e->getMessage(),

            ]);
            $submission->markError($e->getMessage());
            return $submission;
        }
    }

    /**
     * Validate submission data
     */
    public function validateSubmission(SubscriptionForm $form, array $data): array
    {
        $errors = [];
        $fields = $form->fields ?? [];

        foreach ($fields as $field) {
            $fieldId = $field['id'];
            $required = !empty($field['required']);

            // Get value based on field type
            $value = $this->getFieldValue($fieldId, $data);

            // Check required
            if ($required && empty($value)) {
                $label = $field['label'] ?? $fieldId;
                $errors[] = "Pole \"{$label}\" jest wymagane";
                continue;
            }

            // Validate email
            if ($fieldId === 'email' && !empty($value)) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Podaj prawidłowy adres e-mail";
                }
            }

            // Validate phone
            if ($fieldId === 'phone' && !empty($value)) {
                $cleaned = preg_replace('/[^0-9+]/', '', $value);
                if (strlen($cleaned) < 9) {
                    $errors[] = "Podaj prawidłowy numer telefonu";
                }
            }
        }

        // Check policy
        if ($form->require_policy && empty($data['policy'])) {
            $errors[] = "Musisz zaakceptować politykę prywatności";
        }

        return $errors;
    }

    /**
     * Get field value from submission data
     */
    protected function getFieldValue(string $fieldId, array $data): ?string
    {
        // Check direct field
        if (isset($data[$fieldId])) {
            return trim($data[$fieldId]);
        }

        // Check custom fields array - form sends fields[custom_123] format
        if (str_starts_with($fieldId, 'custom_') && isset($data['fields'])) {
            // Form input name is fields[custom_123], so look for the full fieldId
            if (isset($data['fields'][$fieldId])) {
                return trim($data['fields'][$fieldId]);
            }
            // Also check for just the numeric ID for backwards compatibility
            $customId = str_replace('custom_', '', $fieldId);
            if (isset($data['fields'][$customId])) {
                return trim($data['fields'][$customId]);
            }
        }

        return null;
    }

    /**
     * Verify CAPTCHA token
     */
    public function verifyCaptcha(SubscriptionForm $form, string $token): bool
    {
        if (!$form->captcha_secret_key) {
            return true;
        }

        try {
            $verifyUrl = match ($form->captcha_provider) {
                'recaptcha_v2', 'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
                'hcaptcha' => 'https://hcaptcha.com/siteverify',
                'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                default => null,
            };

            if (!$verifyUrl) {
                return true;
            }

            $response = Http::asForm()->post($verifyUrl, [
                'secret' => $form->captcha_secret_key,
                'response' => $token,
            ]);

            return $response->json('success', false);

        } catch (\Exception $e) {
            Log::warning('CAPTCHA verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check honeypot field
     */
    public function checkHoneypot(array $data): bool
    {
        // If honeypot field is filled, it's likely a bot
        return empty($data['website']);
    }

    /**
     * Create or update subscriber
     * Returns array with 'subscriber' and 'isNew' flag
     */
    public function createOrUpdateSubscriber(array $data, ContactList $list, SubscriptionForm $form): array
    {
        $email = strtolower(trim($data['email']));
        $isNew = false;

        // Check if email was previously suppressed (GDPR forgotten)
        // If so, allow re-subscription and log the consent renewal
        $wasSuppressed = SuppressionList::handleResubscription($list->user_id, $email, 'form:' . $form->slug);

        if ($wasSuppressed) {
            Log::info('Subscriber re-subscribed after GDPR deletion', [
                'email' => $email,
                'form_slug' => $form->slug,
                'list_id' => $list->id,
            ]);
        }

        // Check if subscriber exists for this user (including soft-deleted ones)
        // This is important because the unique index doesn't exclude soft-deleted records
        $subscriber = Subscriber::withTrashed()
            ->where('email', $email)
            ->where('user_id', $list->user_id)
            ->first();

        if (!$subscriber) {
            $subscriber = new Subscriber();
            $subscriber->email = $email;
            $subscriber->user_id = $list->user_id;
            $isNew = true;
        } elseif ($subscriber->trashed()) {
            // Restore soft-deleted subscriber
            $subscriber->restore();
            $isNew = false; // Not technically new, but was deleted
        }

        // Update basic fields
        if (!empty($data['fname'])) {
            $subscriber->first_name = trim($data['fname']);
        }
        if (!empty($data['lname'])) {
            $subscriber->last_name = trim($data['lname']);
        }
        if (!empty($data['phone'])) {
            $subscriber->phone = trim($data['phone']);
        }

        // Detect gender from first name if not set
        if (empty($subscriber->gender) && !empty($subscriber->first_name)) {
            $subscriber->gender = $this->detectGender($subscriber->first_name);
        }

        $subscriber->save();

        // Check if already subscribed to this list
        $wasSubscribed = $subscriber->contactLists()->where('contact_list_id', $list->id)->exists();
        $previousStatus = null;

        if ($wasSubscribed) {
            // Get previous subscription status
            $pivot = $subscriber->contactLists()->where('contact_list_id', $list->id)->first();
            $previousStatus = $pivot->pivot->status ?? null;
        }

        // Subscribe to list (if not already)
        // Determine if we should reset the subscribed_at date
        $wasActive = $previousStatus === 'active';
        $shouldResetDate = !$wasActive || ($list->resubscription_behavior ?? 'reset_date') === 'reset_date';

        $pivotData = [
            'status' => $form->shouldUseDoubleOptin() ? 'pending' : 'active',
            'source' => 'form:' . $form->slug,
            'unsubscribed_at' => null,
        ];

        // Only set subscribed_at if we should reset the date or it's a new subscription
        if (!$wasSubscribed || $shouldResetDate) {
            $pivotData['subscribed_at'] = now();
        }

        if (!$wasSubscribed) {
            $subscriber->contactLists()->attach($list->id, $pivotData);
        } else {
            // Update existing subscription
            $subscriber->contactLists()->updateExistingPivot($list->id, $pivotData);
        }

        // Save custom fields
        if (!empty($data['fields'])) {
            $this->saveCustomFields($subscriber, $data['fields']);
        }

        return [
            'subscriber' => $subscriber,
            'isNew' => $isNew,
            'wasSubscribed' => $wasSubscribed,
            'previousStatus' => $previousStatus,
        ];
    }


    /**
     * Save custom field values
     */
    protected function saveCustomFields(Subscriber $subscriber, array $fields): void
    {
        foreach ($fields as $fieldId => $value) {
            if (empty($value)) continue;

            // Field IDs come in format "custom_123" from form, extract numeric ID
            $numericId = $fieldId;
            if (str_starts_with($fieldId, 'custom_')) {
                $numericId = str_replace('custom_', '', $fieldId);
            }

            $customField = CustomField::find($numericId);
            if (!$customField) continue;

            $subscriber->fieldValues()->updateOrCreate(
                ['custom_field_id' => $numericId],
                ['value' => $value]
            );
        }
    }

    /**
     * Detect gender from first name (Polish names)
     */
    protected function detectGender(string $firstName): ?string
    {
        $name = mb_strtolower(trim($firstName));

        // Polish female name endings
        if (preg_match('/(a|ia|ja)$/u', $name) && !preg_match('/(ia|ja)$/u', $name)) {
            return 'female';
        }

        // Common male endings
        if (preg_match('/(ek|aw|an|sz|rz|cz|ej|aj)$/u', $name)) {
            return 'male';
        }

        return null;
    }

    /**
     * Handle co-registration to additional lists
     */
    public function handleCoregistration(Subscriber $subscriber, array $listIds): void
    {
        foreach ($listIds as $listId) {
            $list = ContactList::find($listId);
            if (!$list) continue;

            // Check if subscriber already on this list
            if ($subscriber->contactLists()->where('contact_list_id', $listId)->exists()) {
                continue;
            }

            $subscriber->contactLists()->attach($listId, [
                'status' => 'active',
                'source' => 'coregistration',
                'subscribed_at' => now(),
            ]);
        }
    }

    /**
     * Trigger webhook integrations
     */
    public function triggerIntegrations(FormSubmission $submission): void
    {
        $form = $submission->form;
        $integrations = $form->integrations()->active()->webhooks()->get();

        foreach ($integrations as $integration) {
            // Check if should trigger for this event
            if (!$integration->shouldTriggerFor('submission')) {
                continue;
            }

            try {
                $payload = $integration->formatPayload($submission);
                $integration->trigger($payload);
            } catch (\Exception $e) {
                Log::error('Integration trigger failed', [
                    'integration_id' => $integration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get submission stats for a form
     */
    public function getStats(SubscriptionForm $form, ?string $from = null, ?string $to = null): array
    {
        $query = FormSubmission::forForm($form->id);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return [
            'total' => $query->count(),
            'confirmed' => (clone $query)->confirmed()->count(),
            'pending' => (clone $query)->pending()->count(),
            'rejected' => (clone $query)->rejected()->count(),
            'error' => (clone $query)->error()->count(),
            'by_day' => $this->getSubmissionsByDay($form->id, $from, $to),
            'by_source' => $this->getSubmissionsBySource($form->id, $from, $to),
        ];
    }

    /**
     * Get submissions grouped by day
     */
    protected function getSubmissionsByDay(int $formId, ?string $from, ?string $to): array
    {
        $query = FormSubmission::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('subscription_form_id', $formId)
            ->groupBy('date')
            ->orderBy('date');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->toArray();
    }

    /**
     * Get submissions grouped by source
     */
    protected function getSubmissionsBySource(int $formId, ?string $from, ?string $to): array
    {
        $query = FormSubmission::selectRaw('source, COUNT(*) as count')
            ->where('subscription_form_id', $formId)
            ->groupBy('source');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get()->toArray();
    }

    /**
     * Link visitor device to subscriber for pixel tracking
     */
    protected function linkVisitorToSubscriber(string $visitorToken, Subscriber $subscriber, int $userId): void
    {
        try {
            // Link all devices with this visitor token to the subscriber
            $linkedCount = \App\Models\SubscriberDevice::linkVisitorToSubscriber(
                $visitorToken,
                $userId,
                $subscriber->id
            );

            if ($linkedCount > 0) {
                Log::info('Linked visitor devices to subscriber', [
                    'visitor_token' => $visitorToken,
                    'subscriber_id' => $subscriber->id,
                    'devices_linked' => $linkedCount,
                ]);
            }
        } catch (\Exception $e) {
            // Non-critical, log and continue
            Log::warning('Failed to link visitor to subscriber', [
                'visitor_token' => $visitorToken,
                'subscriber_id' => $subscriber->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
