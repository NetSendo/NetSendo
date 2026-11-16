<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DKIM selectors entered by the user (comma separated). Needed for relays such as
     * Amazon SES that sign with per-domain token selectors which cannot be guessed.
     */
    public function up(): void
    {
        if (Schema::hasColumn('domain_configurations', 'dkim_selector')) {
            return;
        }

        Schema::table('domain_configurations', function (Blueprint $table) {
            $table->string('dkim_selector', 255)->nullable()->after('dkim_status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('domain_configurations', 'dkim_selector')) {
            Schema::table('domain_configurations', function (Blueprint $table) {
                $table->dropColumn('dkim_selector');
            });
        }
    }
};
