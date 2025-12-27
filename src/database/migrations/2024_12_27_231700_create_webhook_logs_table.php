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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('webhook_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event', 100);
            $table->string('url', 500);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
