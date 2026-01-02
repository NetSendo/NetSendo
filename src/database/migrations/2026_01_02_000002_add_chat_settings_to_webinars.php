<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if column already exists (idempotent migration)
        if (Schema::hasColumn('webinars', 'chat_settings')) {
            return;
        }

        Schema::table('webinars', function (Blueprint $table) {
            $table->json('chat_settings')->nullable()->after('settings');
            // Structure:
            // {
            //   "enabled": true,
            //   "mode": "open", // open, moderated, qa_only, host_only
            //   "slow_mode_seconds": 0, // 0 = disabled, otherwise delay between messages
            //   "fake_viewers_base": 50,
            //   "fake_viewers_variance": 20,
            //   "reactions_enabled": true,
            //   "allow_questions": true,
            //   "require_approval": false
            // }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropColumn('chat_settings');
        });
    }
};
