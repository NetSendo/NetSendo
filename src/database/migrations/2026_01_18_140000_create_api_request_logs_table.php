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
        if (Schema::hasTable('api_request_logs')) {
            return;
        }

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('api_key_id')->nullable()->constrained()->onDelete('set null');
            $table->string('method', 10);
            $table->string('endpoint', 255);
            $table->json('request_body')->nullable();
            $table->integer('response_status');
            $table->json('response_body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index(['response_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
