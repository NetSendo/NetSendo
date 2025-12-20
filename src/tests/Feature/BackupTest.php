<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BackupTest extends TestCase
{
    /**
     * Test if backup commands are scheduled.
     */
    public function test_backup_commands_are_scheduled(): void
    {
        $schedule = app()->make(Schedule::class);

        $events = collect($schedule->events())->filter(function (Event $event) {
            return stripos($event->command, 'backup:run') !== false;
        });

        $this->assertTrue($events->count() > 0, 'backup:run command is not scheduled');

        $events = collect($schedule->events())->filter(function (Event $event) {
            return stripos($event->command, 'backup:clean') !== false;
        });

        $this->assertTrue($events->count() > 0, 'backup:clean command is not scheduled');
    }
}
