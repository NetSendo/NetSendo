<?php

namespace App\Console;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Lets only one `php artisan migrate` or `php artisan db:seed` at a time work on a MySQL database.
 *
 * Every NetSendo container (app, scheduler, queue, reverb) migrates and seeds when it starts, and
 * `docker compose up -d` starts them together. docker/php/docker-entrypoint.sh coordinates that
 * itself, but docker-compose.yml mounts the installation's own copy of the entrypoint over the one
 * in the image, so an installation with an older copy still started four migrations at once
 * ("Table ... already exists", seeding into tables that did not exist yet). This lock ships in the
 * image, so with any entrypoint those commands now wait for each other.
 *
 * It is a MySQL named lock (GET_LOCK) on a connection of its own, released when the command
 * finishes. MySQL also drops it when the process ends or dies, so it cannot outlive a crashed
 * container. Other drivers (sqlite in the tests) are left alone, and when the lock cannot be
 * taken because of an error the command runs without it, as before. Its name differs from the
 * entrypoint's lock, which is held by another process while the entrypoint runs these commands.
 */
class MigrationLock
{
    /** Commands that change the schema or insert seed data on every container start */
    public const COMMANDS = ['migrate', 'db:seed'];

    public const CONNECTION = 'netsendo_migration_lock';

    /** Seconds between "still waiting" messages */
    private const REPORT_EVERY = 60;

    /** Commands started and not finished yet; only the outermost one takes the lock */
    private int $depth = 0;

    private bool $locked = false;

    private string $name = '';

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Config $config,
    ) {
    }

    public function commandStarting(CommandStarting $event): void
    {
        if (! in_array($event->command, self::COMMANDS, true)) {
            return;
        }

        $connection = $event->input->getParameterOption('--database', null);

        $this->acquire(is_string($connection) && $connection !== '' ? $connection : null, $event->output);
    }

    public function commandFinished(CommandFinished $event): void
    {
        if (in_array($event->command, self::COMMANDS, true)) {
            $this->release();
        }
    }

    /**
     * Wait until no other process migrates or seeds the database, then hold the lock.
     *
     * @throws RuntimeException when another process keeps the lock longer than
     *                          netsendo.migrations_wait_timeout seconds
     */
    public function acquire(?string $connection, OutputInterface $output): void
    {
        if (++$this->depth > 1) {
            return;
        }

        try {
            $settings = $this->config->get('database.connections.'.($connection ?? $this->config->get('database.default')));
            if (! is_array($settings) || ! in_array($settings['driver'] ?? null, ['mysql', 'mariadb'], true)) {
                return;
            }

            $this->config->set('database.connections.'.self::CONNECTION, $settings);
            $lock = $this->db->connection(self::CONNECTION);

            // An idle session would be closed after wait_timeout, and the lock with it
            $lock->statement('SET SESSION wait_timeout = 86400');

            // Lock names are server-wide: the database name keeps installations sharing a server apart
            $this->name = 'netsendo:migrate:'.substr(sha1((string) $lock->getDatabaseName()), 0, 16);
        } catch (Throwable $e) {
            $this->disconnect();
            $output->writeln("<comment>Running without the migration lock: {$e->getMessage()}</comment>");

            return;
        }

        $timeout = max(0, (int) $this->config->get('netsendo.migrations_wait_timeout', 1800));
        $started = time();
        $deadline = $started + $timeout;
        $reported = null;

        while (true) {
            try {
                $acquired = $lock->selectOne(
                    'SELECT GET_LOCK(?, ?) AS acquired',
                    [$this->name, max(1, min(10, $deadline - time()))]
                )->acquired ?? null;
            } catch (Throwable $e) {
                $this->disconnect();
                $output->writeln("<comment>Running without the migration lock: {$e->getMessage()}</comment>");

                return;
            }

            if ((string) $acquired === '1') {
                $this->locked = true;

                return;
            }

            if ($acquired === null || time() >= $deadline) {
                $this->disconnect();
                $this->depth--;

                throw new RuntimeException($acquired === null
                    ? 'Could not take the migration lock (GET_LOCK returned NULL).'
                    : "Another process has been migrating or seeding this database for over {$timeout} seconds; not starting a second one.");
            }

            $waited = time() - $started;
            if ($reported === null || $waited - $reported >= self::REPORT_EVERY) {
                $output->writeln($reported === null
                    ? '<comment>Another process is migrating or seeding this database, waiting for it to finish...</comment>'
                    : "<comment>Still waiting for the other migration or seeding ({$waited}s)...</comment>");
                $reported = $waited;
            }
        }
    }

    public function release(): void
    {
        if ($this->depth === 0 || --$this->depth > 0) {
            return;
        }

        if ($this->locked) {
            try {
                $this->db->connection(self::CONNECTION)->select('SELECT RELEASE_LOCK(?)', [$this->name]);
            } catch (Throwable) {
                // Disconnecting below releases it as well
            }
        }

        $this->disconnect();
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    private function disconnect(): void
    {
        $this->locked = false;

        try {
            $this->db->purge(self::CONNECTION);
        } catch (Throwable) {
            // Nothing to close
        }
    }
}
