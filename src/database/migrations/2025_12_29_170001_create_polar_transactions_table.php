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
        Schema::create('polar_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('polar_product_id')->nullable()->constrained('polar_products')->onDelete('set null');

            // Polar IDs
            $table->string('polar_checkout_id')->nullable()->index();
            $table->string('polar_order_id')->nullable()->index();
            $table->string('polar_subscription_id')->nullable()->index();
            $table->string('polar_customer_id')->nullable()->index();

            // Customer info
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();

            // Transaction details
            $table->integer('amount'); // in smallest currency unit
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'refunded', 'failed', 'canceled'])->default('pending');
            $table->enum('type', ['one_time', 'subscription', 'renewal'])->default('one_time');

            // Reference to subscriber if linked
            $table->foreignId('subscriber_id')->nullable()->constrained('subscribers')->onDelete('set null');

            // Additional data
            $table->json('metadata')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['customer_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polar_transactions');
    }
};
