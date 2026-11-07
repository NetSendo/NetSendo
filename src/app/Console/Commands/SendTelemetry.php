<?php

namespace App\Console\Commands;

use App\Services\Telemetry\TelemetryReport;
use App\Services\Telemetry\TelemetryService;
use Illuminate\Console\Command;

/**
 * Sends the anonymous daily usage report when the administrator has switched telemetry on
 * (docs/TELEMETRY.md). Scheduled hourly; it sends once a day, in an hour derived from the
 * installation's random id. With telemetry off it does nothing.
 */
class SendTelemetry extends Command
{
    protected $signature = 'netsendo:telemetry
        {--show : Print the report this installation would send, without sending it}
        {--now : Send now instead of waiting for this installation\'s hour}';

    protected $description = 'Send the opt-in, anonymous usage report to NetSendo (off unless switched on)';

    public function handle(TelemetryService $telemetry, TelemetryReport $report): int
    {
        if ($this->option('show')) {
            $id = $telemetry->instanceId() ?? '00000000-0000-4000-8000-000000000000';
            $this->line(json_encode($report->build($id), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info($telemetry->enabled() ? 'Telemetry is on.' : 'Telemetry is off: nothing is sent.');

            return self::SUCCESS;
        }

        if (! $telemetry->enabled()) {
            $this->info('Telemetry is off: nothing is sent.');

            return self::SUCCESS;
        }

        if (! $this->option('now') && ! $telemetry->due()) {
            return self::SUCCESS;
        }

        $id = $telemetry->ensureInstanceId();
        if ($telemetry->send($report->build($id))) {
            $this->info('Telemetry report sent.');
        } else {
            $this->warn('Telemetry report could not be sent; it will be tried again tomorrow.');
        }

        return self::SUCCESS;
    }
}
