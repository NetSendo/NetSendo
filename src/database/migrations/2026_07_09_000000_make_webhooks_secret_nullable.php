<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make webhooks.secret nullable (GitHub #20).
 *
 * The column was NOT NULL with no default, so any INSERT that did not
 * explicitly provide a secret aborted with SQLSTATE[HY000] 1364
 * "Field 'secret' doesn't have a default value". The Webhook model now
 * auto-generates the secret on create; this migration additionally relaxes
 * the DB constraint so raw inserts can no longer fail the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('webhooks') || !Schema::hasColumn('webhooks', 'secret')) {
            return;
        }

        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('secret', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('webhooks') || !Schema::hasColumn('webhooks', 'secret')) {
            return;
        }

        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('secret', 64)->nullable(false)->change();
        });
    }
};
