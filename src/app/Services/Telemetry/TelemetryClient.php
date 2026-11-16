<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to https://netsendo.com/api/telemetry. Failures are logged and swallowed: telemetry
 * must never affect the installation, and a report that fails waits for the next day.
 */
class TelemetryClient
{
    public function url(): string
    {
        return rtrim((string) config('netsendo.telemetry.url'), '/');
    }

    public function send(array $report): bool
    {
        if ($this->url() === '') {
            return false;
        }
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withUserAgent('NetSendo/' . config('netsendo.version') . ' telemetry')
                ->post($this->url(), $report);
            if (! $response->successful()) {
                Log::warning('Telemetry report was not accepted', ['status' => $response->status()]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telemetry report could not be sent: ' . $e->getMessage());

            return false;
        }
    }

    public function delete(string $instanceId): bool
    {
        if ($this->url() === '') {
            return false;
        }
        try {
            return Http::timeout(10)
                ->acceptJson()
                ->withUserAgent('NetSendo/' . config('netsendo.version') . ' telemetry')
                ->delete($this->url() . '/' . $instanceId)
                ->successful();
        } catch (\Throwable $e) {
            Log::warning('Telemetry deletion request could not be sent: ' . $e->getMessage());

            return false;
        }
    }
}
