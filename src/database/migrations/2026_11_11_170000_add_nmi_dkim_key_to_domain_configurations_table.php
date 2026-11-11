<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The DKIM key NetSendo's own MTA signs a domain's mail with. Until now a key existed
     * per dedicated IP only (dedicated_ip_addresses.dkim_*), so an NMI mailbox without a
     * dedicated IP went out unsigned.
     *
     * `dkim_selector` (2026-11-08) is something else: the selectors of other senders that
     * the Deliverability check should look for.
     */
    public function up(): void
    {
        Schema::table('domain_configurations', function (Blueprint $table) {
            if (! Schema::hasColumn('domain_configurations', 'nmi_dkim_selector')) {
                $table->string('nmi_dkim_selector', 63)->nullable()->after('nmi_credentials_rotated_at');
            }
            if (! Schema::hasColumn('domain_configurations', 'nmi_dkim_private_key')) {
                $table->text('nmi_dkim_private_key')->nullable()->after('nmi_dkim_selector'); // Encrypted
            }
            if (! Schema::hasColumn('domain_configurations', 'nmi_dkim_public_key')) {
                $table->text('nmi_dkim_public_key')->nullable()->after('nmi_dkim_private_key');
            }
            if (! Schema::hasColumn('domain_configurations', 'nmi_dkim_generated_at')) {
                $table->timestamp('nmi_dkim_generated_at')->nullable()->after('nmi_dkim_public_key');
            }
            // Set once the DNS record was seen: mail is signed from then on
            if (! Schema::hasColumn('domain_configurations', 'nmi_dkim_verified_at')) {
                $table->timestamp('nmi_dkim_verified_at')->nullable()->after('nmi_dkim_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domain_configurations', function (Blueprint $table) {
            foreach (['nmi_dkim_verified_at', 'nmi_dkim_generated_at', 'nmi_dkim_public_key', 'nmi_dkim_private_key', 'nmi_dkim_selector'] as $column) {
                if (Schema::hasColumn('domain_configurations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
