<?php

namespace App\Console\Commands;

use App\Models\UserCalendarConnection;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshCalendarChannels extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'calendar:refresh-channels';

    /**
     * The console command description.
     */
    protected $description = 'Refresh Google Calendar push notification channels that are expiring soon';

    public function __construct(
        private GoogleCalendarService $calendarService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connections = UserCalendarConnection::needsChannelRefresh()->get();

        if ($connections->isEmpty()) {
            $this->info('No channels need refreshing.');
            return Command::SUCCESS;
        }

        $this->info("Found {$connections->count()} channels to refresh.");

        $refreshed = 0;
        $failed = 0;

        foreach ($connections as $connection) {
            try {
                // Stop existing watch first
                if ($connection->channel_id && $connection->resource_id) {
                    $this->calendarService->stopWatch($connection);
                }

                // Set up new watch
                $this->calendarService->watchCalendar($connection);
                $refreshed++;

                $this->info("Refreshed channel for connection ID: {$connection->id}");

            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to refresh Calendar channel', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to refresh connection ID: {$connection->id} - {$e->getMessage()}");
            }
        }

        $this->info("Completed. Refreshed: {$refreshed}, Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
