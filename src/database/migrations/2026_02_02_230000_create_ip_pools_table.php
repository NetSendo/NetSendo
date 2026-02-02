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
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // null = shared pool

            $table->string('name');
            $table->enum('type', ['shared', 'dedicated'])->default('dedicated');
            $table->text('description')->nullable();

            // Pool status
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_ips')->default(10);

            // Default warming schedule
            $table->json('warming_schedule')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_pools');
    }
};
