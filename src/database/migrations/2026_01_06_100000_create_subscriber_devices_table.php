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
        Schema::create('subscriber_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_token', 64)->index();
            $table->string('device_fingerprint', 128)->nullable()->index();
            $table->enum('device_type', ['desktop', 'mobile', 'tablet'])->default('desktop');
            $table->string('browser', 100)->nullable();
            $table->string('browser_version', 50)->nullable();
            $table->string('os', 100)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->string('screen_resolution', 20)->nullable();
            $table->string('language', 10)->nullable();
            $table->string('timezone', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index(['user_id', 'visitor_token']);
            $table->index('subscriber_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriber_devices');
    }
};
