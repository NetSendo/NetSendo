<?php

namespace App\Services\Telemetry;

use App\Models\AbTest;
use App\Models\AiConversation;
use App\Models\AiIntegration;
use App\Models\ApiKey;
use App\Models\AutomationRule;
use App\Models\CalendlyIntegration;
use App\Models\ContactList;
use App\Models\CrmContact;
use App\Models\Funnel;
use App\Models\Mailbox;
use App\Models\MessageQueueEntry;
use App\Models\Message;
use App\Models\PluginConnection;
use App\Models\Setting;
use App\Models\SmsProvider;
use App\Models\Subscriber;
use App\Models\SubscriptionForm;
use App\Models\User;
use App\Models\UserCalendarConnection;
use App\Models\UserZoomConnection;
use App\Models\Webhook;
use App\Models\Webinar;
use App\Models\WooCommerceSettings;
use App\Services\LicenseService;
use Illuminate\Support\Facades\DB;

/**
 * Builds the anonymous usage report (docs/TELEMETRY.md). Every value is a version, a range,
 * a true/false flag or a provider type; nothing names or identifies the installation, its
 * users or its contacts. The format matches lib/telemetry.ts in NetSendo/NetSendo.com, which
 * stores only the fields defined there. Each value is read on its own, so a missing table or
 * a failing query leaves that value out instead of failing the report.
 */
class TelemetryReport
{
    public const SCHEMA = 1;

    /** Ranges for every count */
    public const BUCKETS = ['0', '1-10', '11-100', '101-1k', '1k-10k', '10k-100k', '100k-1m', '1m+'];

    public static function bucket(int $count): string
    {
        return match (true) {
            $count <= 0 => '0',
            $count <= 10 => '1-10',
            $count <= 100 => '11-100',
            $count <= 1_000 => '101-1k',
            $count <= 10_000 => '1k-10k',
            $count <= 100_000 => '10k-100k',
            $count <= 1_000_000 => '100k-1m',
            default => '1m+',
        };
    }

    public function build(string $instanceId): array
    {
        $since = now()->subDays(30);

        $report = [
            'schema' => self::SCHEMA,
            'instance_id' => $instanceId,
            'app' => array_filter([
                'version' => (string) config('netsendo.version'),
                'plan' => $this->plan(),
                'deployment' => $this->deployment(),
                'locale' => $this->locale(),
            ], fn ($v) => $v !== null),
            'platform' => array_filter([
                'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                'database' => $this->value(fn () => $this->database()['engine']),
                'database_version' => $this->value(fn () => $this->database()['version']),
                'queue' => $this->queue(),
            ], fn ($v) => $v !== null),
            'usage' => array_filter([
                'contacts' => $this->count(fn () => Subscriber::count()),
                'email_lists' => $this->count(fn () => ContactList::where('type', 'email')->count()),
                'sms_lists' => $this->count(fn () => ContactList::where('type', 'sms')->count()),
                'users' => $this->count(fn () => User::count()),
                'campaigns_sent_30d' => $this->count(fn () => Message::where('channel', 'email')->where('type', 'broadcast')
                    ->where('status', 'sent')->where('updated_at', '>=', $since)->count()),
                'emails_sent_30d' => $this->count(fn () => $this->sentSince('email', $since)),
                'sms_sent_30d' => $this->count(fn () => $this->sentSince('sms', $since)),
                'active_funnels' => $this->count(fn () => Funnel::where('status', 'active')->count()),
                'active_automations' => $this->count(fn () => AutomationRule::where('is_active', true)->count()),
                'webinars' => $this->count(fn () => Webinar::count()),
                'forms' => $this->count(fn () => SubscriptionForm::count()),
                'mailboxes' => $this->count(fn () => Mailbox::count()),
            ], fn ($v) => $v !== null),
            'features' => array_filter([
                'sms' => $this->flag(fn () => SmsProvider::where('is_active', true)->exists()),
                'ai' => $this->flag(fn () => AiIntegration::where('is_active', true)->exists()),
                'brain' => $this->flag(fn () => AiConversation::query()->exists()),
                'mcp' => $this->flag(fn () => ApiKey::where('is_mcp', true)->exists()),
                'api_keys' => $this->flag(fn () => ApiKey::where('is_mcp', false)->exists()),
                'webhooks' => $this->flag(fn () => Webhook::where('is_active', true)->exists()),
                'crm' => $this->flag(fn () => CrmContact::query()->exists()),
                'webinars' => $this->flag(fn () => Webinar::query()->exists()),
                'ab_tests' => $this->flag(fn () => AbTest::query()->exists()),
                'woocommerce' => $this->flag(fn () => PluginConnection::where('plugin_type', 'woocommerce')->where('is_active', true)->exists()
                    || WooCommerceSettings::where('is_active', true)->exists()),
                'shopify' => $this->flag(fn () => Subscriber::where('source', 'shopify')->exists()),
                'wordpress' => $this->flag(fn () => PluginConnection::where('plugin_type', 'wordpress')->where('is_active', true)->exists()),
                'n8n' => $this->flag(fn () => PluginConnection::where('plugin_type', 'n8n')->where('is_active', true)->exists()),
                'stripe' => $this->flag(fn () => $this->settingSet(['stripe_secret_key', 'stripe_oauth_stripe_user_id'])),
                'polar' => $this->flag(fn () => $this->settingSet(['polar_access_token'])),
                'calendly' => $this->flag(fn () => CalendlyIntegration::query()->active()->exists()),
                'google_calendar' => $this->flag(fn () => UserCalendarConnection::where('is_active', true)->exists()),
                'zoom' => $this->flag(fn () => UserZoomConnection::where('is_active', true)->exists()),
            ], fn ($v) => $v !== null),
            'providers' => array_filter([
                'mail' => $this->types(fn () => Mailbox::query()->distinct()->pluck('provider')),
                'sms' => $this->types(fn () => SmsProvider::where('is_active', true)->distinct()->pluck('provider')),
                'ai' => $this->types(fn () => AiIntegration::where('is_active', true)->distinct()->pluck('provider')),
            ], fn ($v) => $v !== null),
        ];

        return array_filter($report, fn ($v) => $v !== []);
    }

    private function plan(): string
    {
        try {
            if (! Setting::where('key', 'license_key')->exists()) {
                return 'none';
            }

            return strtolower(app(LicenseService::class)->getCurrentPlan()) === 'gold' ? 'gold' : 'silver';
        } catch (\Throwable) {
            return 'none';
        }
    }

    private function deployment(): string
    {
        $configured = config('netsendo.deployment');
        if (in_array($configured, ['docker', 'cloud', 'other'], true)) {
            return $configured;
        }

        return file_exists('/.dockerenv') ? 'docker' : 'other';
    }

    private function locale(): ?string
    {
        $locale = substr((string) config('app.locale'), 0, 2);

        return in_array($locale, ['en', 'pl', 'de', 'es'], true) ? $locale : null;
    }

    private function queue(): ?string
    {
        $driver = (string) config('queue.default');

        return in_array($driver, ['redis', 'database', 'sync', 'sqs'], true) ? $driver : ($driver ? 'other' : null);
    }

    /** @return array{engine: string, version: ?string} */
    private function database(): array
    {
        static $info = null;
        if ($info !== null) {
            return $info;
        }
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $raw = (string) $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $engine = match (true) {
            $driver === 'mysql' && stripos($raw, 'mariadb') !== false, $driver === 'mariadb' => 'mariadb',
            in_array($driver, ['mysql', 'pgsql', 'sqlite'], true) => $driver,
            default => 'other',
        };
        $version = preg_match('/(\d+)\.(\d+)/', $raw, $m) ? "{$m[1]}.{$m[2]}" : null;

        return $info = ['engine' => $engine, 'version' => $version];
    }

    /** Emails or SMS delivered from the queue since a date */
    private function sentSince(string $channel, $since): int
    {
        return MessageQueueEntry::query()
            ->where('message_queue_entries.status', 'sent')
            ->where('message_queue_entries.sent_at', '>=', $since)
            ->whereHas('message', fn ($q) => $q->withTrashed()->where('channel', $channel))
            ->count();
    }

    private function settingSet(array $keys): bool
    {
        return Setting::whereIn('key', $keys)->whereNotNull('value')->where('value', '!=', '')->exists();
    }

    private function count(callable $query): ?string
    {
        $value = $this->value($query);

        return $value === null ? null : self::bucket((int) $value);
    }

    private function flag(callable $query): ?bool
    {
        $value = $this->value($query);

        return $value === null ? null : (bool) $value;
    }

    /** Provider types only, lower-case slugs, never host names or accounts */
    private function types(callable $query): ?array
    {
        $values = $this->value($query);
        if ($values === null) {
            return null;
        }
        $types = collect($values)
            ->map(fn ($v) => strtolower((string) $v))
            ->filter(fn ($v) => preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $v))
            ->unique()->sort()->values()->take(12)->all();

        return $types === [] ? null : $types;
    }

    private function value(callable $query): mixed
    {
        try {
            return $query();
        } catch (\Throwable) {
            return null;
        }
    }
}
