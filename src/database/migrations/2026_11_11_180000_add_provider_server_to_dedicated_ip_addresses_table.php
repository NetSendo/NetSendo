<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An address ordered through a provider's API belongs to one server there: its id is
     * needed again to set reverse DNS and to give the address back. Where the provider
     * translates the public address to a private one on the server, `bind_address` is the
     * private side (what goes into NMI_BIND_MAP).
     */
    public function up(): void
    {
        Schema::table('dedicated_ip_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('dedicated_ip_addresses', 'provider_server_id')) {
                $table->string('provider_server_id', 64)->nullable()->after('provider_id');
            }
            if (! Schema::hasColumn('dedicated_ip_addresses', 'bind_address')) {
                $table->string('bind_address', 45)->nullable()->after('provider_region');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dedicated_ip_addresses', function (Blueprint $table) {
            foreach (['bind_address', 'provider_server_id'] as $column) {
                if (Schema::hasColumn('dedicated_ip_addresses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
