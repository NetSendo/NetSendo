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
        Schema::create('user_calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_integration_id')->constrained()->cascadeOnDelete();

            // OAuth tokens (encrypted at application level)
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();

            // Calendar settings
            $table->string('calendar_id')->default('primary');
            $table->string('connected_email')->nullable();

            // Push notification channel (for real-time sync from Google)
            $table->string('channel_id')->nullable();
            $table->string('resource_id')->nullable();
            $table->timestamp('channel_expires_at')->nullable();

            // Sync settings
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_sync_tasks')->default(true);
            $table->json('sync_settings')->nullable();
            $table->string('sync_token')->nullable();
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // One connection per user per integration
            $table->unique(['user_id', 'google_integration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_calendar_connections');
    }
};
