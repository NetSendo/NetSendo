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
        Schema::create('mcp_status', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->string('message', 500)->nullable();
            $table->string('version', 50)->nullable();
            $table->string('api_url', 255)->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();

            // Index for quick status lookup
            $table->index('tested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_status');
    }
};
