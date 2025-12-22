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
        Schema::create('email_read_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 64)->unique();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('read_time_seconds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('visibility_events')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'subscriber_id']);
            $table->index('session_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_read_sessions');
    }
};
