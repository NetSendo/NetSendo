<?php

namespace Tests\Feature;

use App\Jobs\RestoreBackupJob;
use App\Jobs\RunBackupJob;
use App\Models\BackupOperation;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_creating_a_backup_queues_a_job_and_records_a_running_operation(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('settings.backup.create'), [
            'only_db' => true,
        ]);

        $response->assertRedirect(route('settings.backup.index'));

        Queue::assertPushed(RunBackupJob::class);
        $this->assertDatabaseHas('backup_operations', [
            'type' => 'create',
            'status' => 'running',
            'only_db' => true,
        ]);
    }

    public function test_a_second_operation_is_blocked_while_one_is_running(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        BackupOperation::create([
            'type' => 'create',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->post(route('settings.backup.create'));

        Queue::assertNotPushed(RunBackupJob::class);
        $this->assertSame(1, BackupOperation::count());
    }

    public function test_restoring_a_missing_backup_returns_404(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('settings.backup.restore', 'does-not-exist.zip'));

        $response->assertNotFound();
        Queue::assertNotPushed(RestoreBackupJob::class);
    }
}
