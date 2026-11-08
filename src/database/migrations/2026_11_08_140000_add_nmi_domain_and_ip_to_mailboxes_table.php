<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A NetSendo MTA (NMI) mailbox sends for one Deliverability Shield domain through one
     * dedicated IP. Mailbox::domainConfiguration() / dedicatedIp() and MailProviderService
     * read these columns, but they were never created, so no NMI mailbox could be built.
     */
    public function up(): void
    {
        Schema::table('mailboxes', function (Blueprint $table) {
            if (!Schema::hasColumn('mailboxes', 'domain_configuration_id')) {
                $table->foreignId('domain_configuration_id')->nullable()->after('google_integration_id')
                    ->constrained('domain_configurations')->nullOnDelete();
            }
            if (!Schema::hasColumn('mailboxes', 'dedicated_ip_id')) {
                $table->foreignId('dedicated_ip_id')->nullable()->after('domain_configuration_id')
                    ->constrained('dedicated_ip_addresses')->nullOnDelete();
            }
        });

        // 2026_02_02_230002 adds 'nmi' to the provider enum on MySQL only, so on SQLite (the
        // test database) the CHECK constraint of the create migration still rejected it.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('mailboxes', function (Blueprint $table) {
                $table->enum('provider', ['smtp', 'sendgrid', 'gmail', 'nmi'])->default('smtp')->change();
            });
        }
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

        // The provider enum is not narrowed back: NMI mailboxes would no longer fit it
    }
};
