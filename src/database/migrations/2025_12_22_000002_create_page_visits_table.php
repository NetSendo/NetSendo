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
        if (!Schema::hasTable('page_visits')) {
            Schema::create('page_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('page_url', 2048);
                $table->string('page_title')->nullable();
                $table->string('referrer', 2048)->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->string('visitor_token', 64)->nullable();
                $table->unsignedInteger('time_on_page_seconds')->nullable();
                $table->timestamp('visited_at');
                $table->timestamps();

                $table->index('subscriber_id');
                $table->index('user_id');
                $table->index('visitor_token');
                $table->index(['user_id', 'page_url']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
