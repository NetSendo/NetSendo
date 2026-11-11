<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard sums sent e-mails by day over up to 180 days of CRON runs. The only index that
 * covers started_at starts with job_name, so those sums scanned the whole table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('cron_job_logs', 'cron_job_logs_started_at_index')) {
            return;
        }

        Schema::table('cron_job_logs', function (Blueprint $table) {
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('cron_job_logs', 'cron_job_logs_started_at_index')) {
            return;
        }

        Schema::table('cron_job_logs', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
        });
    }
};
