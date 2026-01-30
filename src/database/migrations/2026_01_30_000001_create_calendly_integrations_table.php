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
        Schema::create('calendly_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // OAuth tokens (encrypted in model)
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();

            // Calendly user/organization info
            $table->string('calendly_user_uri')->nullable();
            $table->string('calendly_organization_uri')->nullable();
            $table->string('calendly_user_email')->nullable();
            $table->string('calendly_user_name')->nullable();

            // Webhook subscription
            $table->string('webhook_id')->nullable();
            $table->string('webhook_signing_key')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Settings JSON (event type to list/tag mappings)
            $table->json('settings')->nullable();

            // Cached event types from Calendly
            $table->json('event_types')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('calendly_user_uri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendly_integrations');
    }
};
