<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FormIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_form_id',
        'type',
        'name',
        'status',
        'webhook_url',
        'webhook_method',
        'webhook_headers',
        'webhook_format',
        'auth_type',
        'auth_token',
        'api_key_name',
        'api_key_value',
        'trigger_on',
        'last_triggered_at',
        'success_count',
        'error_count',
        'last_error',
    ];

    protected $casts = [
        'webhook_headers' => 'array',
        'trigger_on' => 'array',
        'last_triggered_at' => 'datetime',
        'success_count' => 'integer',
        'error_count' => 'integer',
    ];

    // ========== RELATIONSHIPS ==========

    public function form(): BelongsTo
    {
        return $this->belongsTo(SubscriptionForm::class, 'subscription_form_id');
    }

    // ========== MUTATORS (encrypted fields) ==========

    public function setAuthTokenAttribute($value)
    {
        $this->attributes['auth_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAuthTokenAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setApiKeyValueAttribute($value)
    {
        $this->attributes['api_key_value'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiKeyValueAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWebhooks($query)
    {
        return $query->whereIn('type', ['webhook', 'zapier', 'make', 'n8n']);
    }

    // ========== WEBHOOK TRIGGER ==========

    /**
     * Trigger the webhook with given payload
     */
    public function trigger(array $payload): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (!$this->webhook_url) {
            return false;
        }

        try {
            $request = Http::timeout(10);

            // Add custom headers
            if (!empty($this->webhook_headers)) {
                $request = $request->withHeaders($this->webhook_headers);
            }

            // Add authentication
            $request = $this->applyAuthentication($request);

            // Send request
            if ($this->webhook_method === 'GET') {
                $response = $request->get($this->webhook_url, $payload);
            } else {
                if ($this->webhook_format === 'form') {
                    $response = $request->asForm()->post($this->webhook_url, $payload);
                } else {
                    $response = $request->post($this->webhook_url, $payload);
                }
            }

            $success = $response->successful();

            // Update stats
            $this->recordTrigger($success, $success ? null : "HTTP {$response->status()}: {$response->body()}");

            return $success;

        } catch (\Exception $e) {
            Log::error("FormIntegration webhook error", [
                'integration_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            $this->recordTrigger(false, $e->getMessage());
            return false;
        }
    }

    /**
     * Apply authentication to HTTP request
     */
    protected function applyAuthentication($request)
    {
        switch ($this->auth_type) {
            case 'bearer':
                return $request->withToken($this->auth_token);

            case 'basic':
                // auth_token format: "username:password"
                $parts = explode(':', $this->auth_token ?? '', 2);
                if (count($parts) === 2) {
                    return $request->withBasicAuth($parts[0], $parts[1]);
                }
                return $request;

            case 'api_key':
                return $request->withHeaders([
                    $this->api_key_name ?? 'X-API-Key' => $this->api_key_value,
                ]);

            default:
                return $request;
        }
    }

    /**
     * Record trigger result
     */
    public function recordTrigger(bool $success, ?string $error = null): void
    {
        if ($success) {
            $this->increment('success_count');
            $this->update([
                'last_triggered_at' => now(),
                'status' => 'active',
                'last_error' => null,
            ]);
        } else {
            $this->increment('error_count');
            $this->update([
                'last_triggered_at' => now(),
                'last_error' => $error,
                'status' => $this->error_count >= 5 ? 'error' : 'active', // Auto-disable after 5 errors
            ]);
        }
    }

    /**
     * Check if integration should trigger for given event
     */
    public function shouldTriggerFor(string $event): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $triggers = $this->trigger_on ?? ['submission'];
        return in_array($event, $triggers);
    }

    /**
     * Format payload for this integration type
     */
    public function formatPayload(FormSubmission $submission): array
    {
        $form = $submission->form;

        $payload = [
            'event' => 'form_submission',
            'timestamp' => now()->toIso8601String(),
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
                'slug' => $form->slug,
            ],
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'data' => $submission->submission_data,
                'ip_address' => $submission->ip_address,
                'source' => $submission->source,
                'created_at' => $submission->created_at->toIso8601String(),
            ],
        ];

        if ($submission->subscriber) {
            $payload['subscriber'] = [
                'id' => $submission->subscriber->id,
                'email' => $submission->subscriber->email,
            ];
        }

        // Platform-specific formatting
        switch ($this->type) {
            case 'zapier':
                // Flatten for Zapier
                return array_merge(
                    ['form_name' => $form->name],
                    $submission->submission_data,
                    ['submission_id' => $submission->id]
                );

            case 'make':
            case 'n8n':
                // These prefer structured data
                return $payload;

            default:
                return $payload;
        }
    }

    /**
     * Send test webhook
     */
    public function sendTest(): array
    {
        $testPayload = [
            'event' => 'test',
            'timestamp' => now()->toIso8601String(),
            'message' => 'This is a test webhook from NetSendo',
            'form' => [
                'id' => $this->subscription_form_id,
                'name' => $this->form->name ?? 'Test Form',
            ],
            'submission' => [
                'id' => 0,
                'data' => [
                    'email' => 'test@example.com',
                    'fname' => 'Jan',
                ],
            ],
        ];

        try {
            $success = $this->trigger($testPayload);
            return [
                'success' => $success,
                'message' => $success ? 'Webhook sent successfully' : ($this->last_error ?? 'Unknown error'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
