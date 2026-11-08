<?php

namespace Tests\Feature;

use App\Console\MigrationLock;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Containers that start together must not run `migrate` / `db:seed` at the same time
 * (docker-compose.yml starts app, scheduler, queue and reverb at once, and an
 * installation's own copy of the entrypoint may be older than the image).
 */
class MigrationLockTest extends TestCase
{
    use RefreshDatabase;

    private Connection $connection;

    private DatabaseManager $db;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.lock_test' => ['driver' => 'mysql', 'host' => 'db', 'database' => 'netsendo'],
            'netsendo.migrations_wait_timeout' => 30,
        ]);

        $this->connection = Mockery::mock(Connection::class);
        $this->connection->shouldReceive('statement')->with('SET SESSION wait_timeout = 86400')->andReturnTrue();
        $this->connection->shouldReceive('getDatabaseName')->andReturn('netsendo');

        $this->db = Mockery::mock(DatabaseManager::class);
        $this->db->shouldReceive('connection')->with(MigrationLock::CONNECTION)->andReturn($this->connection);
        $this->db->shouldReceive('purge')->with(MigrationLock::CONNECTION);
    }

    private function lock(): MigrationLock
    {
        return new MigrationLock($this->db, $this->app['config']);
    }

    private function getLockReturns(...$results): void
    {
        $this->connection->shouldReceive('selectOne')
            ->with('SELECT GET_LOCK(?, ?) AS acquired', Mockery::on(fn ($bindings) => str_starts_with($bindings[0], 'netsendo:migrate:')))
            ->andReturn(...array_map(fn ($acquired) => (object) ['acquired' => $acquired], $results));
    }

    public function test_waits_for_the_other_process_then_holds_the_lock_until_released(): void
    {
        $this->getLockReturns(0, 0, 1);
        $this->connection->shouldReceive('select')->once()->with('SELECT RELEASE_LOCK(?)', Mockery::type('array'));
        $output = new BufferedOutput;

        $lock = $this->lock();
        $lock->acquire('lock_test', $output);

        $this->assertTrue($lock->isLocked());
        $this->assertStringContainsString('Another process is migrating or seeding this database', $output->fetch());

        $lock->release();
        $this->assertFalse($lock->isLocked());
    }

    public function test_gives_up_when_the_other_process_holds_the_lock_too_long(): void
    {
        config(['netsendo.migrations_wait_timeout' => 0]);
        $this->getLockReturns(0);

        $lock = $this->lock();

        try {
            $lock->acquire('lock_test', new BufferedOutput);
            $this->fail('acquire() should give up');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('for over 0 seconds', $e->getMessage());
        }

        $this->assertFalse($lock->isLocked());
    }

    public function test_runs_without_the_lock_when_it_cannot_be_taken(): void
    {
        $this->connection->shouldReceive('selectOne')->andThrow(new RuntimeException('FUNCTION GET_LOCK does not exist'));
        $output = new BufferedOutput;

        $lock = $this->lock();
        $lock->acquire('lock_test', $output);

        $this->assertFalse($lock->isLocked());
        $this->assertStringContainsString('Running without the migration lock', $output->fetch());
    }

    public function test_a_command_called_inside_a_locked_one_does_not_release_the_lock(): void
    {
        $this->getLockReturns(1);
        $this->connection->shouldReceive('select')->once()->with('SELECT RELEASE_LOCK(?)', Mockery::type('array'));

        $lock = $this->lock();
        $lock->acquire('lock_test', new BufferedOutput);
        $lock->acquire('lock_test', new BufferedOutput);

        $lock->release();
        $this->assertTrue($lock->isLocked());

        $lock->release();
        $this->assertFalse($lock->isLocked());
    }

    public function test_only_migrate_and_db_seed_take_the_lock(): void
    {
        $this->getLockReturns(1);
        $this->connection->shouldReceive('select')->once()->with('SELECT RELEASE_LOCK(?)', Mockery::type('array'));
        $lock = $this->lock();
        $input = new ArrayInput(['--database' => 'lock_test']);
        $output = new BufferedOutput;

        $lock->commandStarting(new CommandStarting('queue:work', $input, $output));
        $this->assertFalse($lock->isLocked());

        $lock->commandStarting(new CommandStarting('migrate', $input, $output));
        $this->assertTrue($lock->isLocked());

        $lock->commandFinished(new CommandFinished('migrate', $input, $output, 0));
        $this->assertFalse($lock->isLocked());
    }

    /** Laravel switches the console command events off in unit tests; `php artisan` has them on */
    private function withConsoleEvents(): void
    {
        $kernel = $this->app->make(ConsoleKernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $kernel->setArtisan(null);
    }

    public function test_sqlite_runs_migrate_without_a_lock(): void
    {
        $this->withConsoleEvents();

        $this->artisan('migrate')->assertSuccessful();

        $this->assertFalse($this->app->make(MigrationLock::class)->isLocked());
        $this->assertArrayNotHasKey(MigrationLock::CONNECTION, DB::getConnections());
    }

    public function test_artisan_commands_reach_the_lock_also_through_artisan_call(): void
    {
        $started = [];
        $finished = [];
        $this->mock(MigrationLock::class, function ($mock) use (&$started, &$finished) {
            $mock->shouldReceive('commandStarting')->andReturnUsing(function (CommandStarting $event) use (&$started) {
                $started[] = $event->command;
            });
            $mock->shouldReceive('commandFinished')->andReturnUsing(function (CommandFinished $event) use (&$finished) {
                $finished[] = $event->command;
            });
        });
        $this->withConsoleEvents();

        $this->artisan('migrate')->assertSuccessful();
        // The panel (Admin\AdminMigrationController) runs migrations this way
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(['migrate', 'migrate'], $started);
        $this->assertSame(['migrate', 'migrate'], $finished);
    }
}
