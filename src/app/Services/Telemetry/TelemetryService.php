<?php

namespace App\Services\Telemetry;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Opt-in, anonymous usage reports (docs/TELEMETRY.md). Off by default: nothing is sent
 * until the administrator switches it on in Settings → Telemetry, or NETSENDO_TELEMETRY=true
 * is set. The installation is identified only by a random UUID created when telemetry is
 * switched on; switching it off asks netsendo.com to delete that id's reports and forgets it.
 */
class TelemetryService
{
    public const ENABLED_KEY = 'telemetry_enabled';
    public const INSTANCE_KEY = 'telemetry_instance_id';
    public const LAST_SENT_KEY = 'telemetry_last_sent_at';
    public const NOTICE_KEY = 'telemetry_notice_dismissed_at';

    public function __construct(private TelemetryClient $client) {}

    /** true/false when NETSENDO_TELEMETRY is set (the panel switch is then hidden), else null */
    public function environmentOverride(): ?bool
    {
        $value = config('netsendo.telemetry.enabled');
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function enabled(): bool
    {
        return $this->environmentOverride() ?? $this->setting(self::ENABLED_KEY) === '1';
    }

    public function instanceId(): ?string
    {
        $id = $this->setting(self::INSTANCE_KEY);

        return $id && Str::isUuid($id) ? $id : null;
    }

    public function lastSentAt(): ?Carbon
    {
        $value = $this->setting(self::LAST_SENT_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    /** The administrator switched telemetry on: a new random id is created if there is none */
    public function enable(): void
    {
        $this->put(self::ENABLED_KEY, '1');
        $this->ensureInstanceId();
    }

    /**
     * The administrator switched telemetry off: no more reports, the server is asked to
     * delete this id's reports, and the id is forgotten (a later opt-in gets a new one).
     */
    public function disable(): void
    {
        $this->put(self::ENABLED_KEY, '0');
        $id = $this->instanceId();
        if ($id) {
            $this->client->delete($id);
        }
        Setting::whereIn('key', [self::INSTANCE_KEY, self::LAST_SENT_KEY])->delete();
    }

    public function ensureInstanceId(): string
    {
        $id = $this->instanceId();
        if (! $id) {
            $id = (string) Str::uuid();
            $this->put(self::INSTANCE_KEY, $id);
        }

        return $id;
    }

    /**
     * The hour (UTC) at which this installation reports, spread over the day by its id so
     * that installations do not all report at the same time.
     */
    public function reportHour(string $instanceId): int
    {
        return hexdec(substr(str_replace('-', '', $instanceId), 0, 6)) % 24;
    }

    /**
     * Whether the hourly scheduler run should send now: once a day in this installation's
     * hour, or at once when the last report is more than two days old (the server was down
     * at that hour, or telemetry was just switched on).
     */
    public function due(?Carbon $now = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        $now ??= Carbon::now('UTC');
        $id = $this->ensureInstanceId();
        $last = $this->lastSentAt();
        if (! $last || $last->lt($now->copy()->subDays(2))) {
            return true;
        }

        return $now->hour === $this->reportHour($id) && $last->lt($now->copy()->subHours(20));
    }

    /** Sends the report; true when netsendo.com accepted it */
    public function send(array $report): bool
    {
        $ok = $this->client->send($report);
        if ($ok) {
            $this->put(self::LAST_SENT_KEY, now()->toIso8601String());
        }

        return $ok;
    }

    public function noticeDismissed(): bool
    {
        return $this->setting(self::NOTICE_KEY) !== null;
    }

    public function dismissNotice(): void
    {
        $this->put(self::NOTICE_KEY, now()->toIso8601String());
    }

    /** The administrator should be asked once: telemetry is off, not decided by the environment, not dismissed */
    public function shouldAsk(): bool
    {
        return $this->environmentOverride() === null && ! $this->enabled() && ! $this->noticeDismissed();
    }

    private function setting(string $key): ?string
    {
        return Setting::where('key', $key)->value('value');
    }

    private function put(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
