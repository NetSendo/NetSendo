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
        Schema::create('pixel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_device_id')->constrained('subscriber_devices')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Event classification
            $table->string('event_type', 50)->index(); // page_view, product_view, add_to_cart, checkout, custom
            $table->string('event_category', 50)->nullable(); // ecommerce, navigation, engagement

            // Page information
            $table->string('page_url', 2048);
            $table->string('page_title', 255)->nullable();
            $table->string('referrer', 2048)->nullable();

            // E-commerce specific fields
            $table->string('product_id', 100)->nullable()->index();
            $table->string('product_name', 255)->nullable();
            $table->string('product_category', 255)->nullable();
            $table->decimal('product_price', 10, 2)->nullable();
            $table->string('product_currency', 3)->default('PLN');
            $table->decimal('cart_value', 10, 2)->nullable();

            // Engagement metrics
            $table->unsignedInteger('time_on_page_seconds')->nullable();
            $table->unsignedTinyInteger('scroll_depth_percent')->nullable();

            // Custom data for extensibility
            $table->json('custom_data')->nullable();

            // Metadata
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['user_id', 'event_type']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['subscriber_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pixel_events');
    }
};
