<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROVIDERS = ['smtp', 'sendgrid', 'gmail', 'nmi'];

    /**
     * A mailbox of the "NetSendo MTA" provider (NMI) sends as a verified domain and,
     * optionally, through one of the account's dedicated IPs. Mailbox::domainConfiguration(),
     * Mailbox::dedicatedIp() and DedicatedIpAddress::mailboxes() were written against these
     * two columns, which no migration had created.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            if (! Schema::hasColumn('mailboxes', 'domain_configuration_id')) {
                $table->foreignId('domain_configuration_id')->nullable()->after('google_integration_id')
                    ->constrained('domain_configurations')->nullOnDelete();
            }

            if (! Schema::hasColumn('mailboxes', 'dedicated_ip_id')) {
                $table->foreignId('dedicated_ip_id')->nullable()->after('domain_configuration_id')
                    ->constrained('dedicated_ip_addresses')->nullOnDelete();
            }
        });

        $this->allowNmiProvider();
    }

    public function down(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            if (Schema::hasColumn('mailboxes', 'dedicated_ip_id')) {
                $table->dropConstrainedForeignId('dedicated_ip_id');
            }

            if (Schema::hasColumn('mailboxes', 'domain_configuration_id')) {
                $table->dropConstrainedForeignId('domain_configuration_id');
            }
        });

        // The provider list is left as it is: mailboxes of the 'nmi' provider may exist
    }

    /**
     * mailboxes.provider must accept 'nmi'. The NMI migration of 2026-02 widened the enum
     * with raw MySQL DDL only, so on SQLite (the test database) the CHECK constraint of the
     * create migration still refused it. On MySQL this is a no-op unless the enum was
     * narrowed again.
     */
    private function allowNmiProvider(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $column = DB::selectOne("SHOW COLUMNS FROM mailboxes WHERE Field = 'provider'");
            $type = strtolower((string) ($column->Type ?? ''));

            if (str_starts_with($type, 'enum(') && ! str_contains($type, "'nmi'")) {
                $list = implode(', ', array_map(fn ($provider) => "'{$provider}'", self::PROVIDERS));
                DB::statement("ALTER TABLE mailboxes MODIFY COLUMN provider ENUM({$list}) DEFAULT 'smtp'");
            }
        } elseif ($driver === 'sqlite') {
            Schema::table('mailboxes', function (Blueprint $table) {
                $table->enum('provider', self::PROVIDERS)->default('smtp')->change();
            });
        }
    }
};
