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
        Schema::create('stripe_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('stripe_product_id')->nullable()->constrained('stripe_products')->onDelete('set null');
            $table->foreignId('subscriber_id')->nullable()->constrained('subscribers')->onDelete('set null');

            // Stripe IDs
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_charge_id')->nullable()->index();

            // Customer info
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_name')->nullable();

            // Transaction details
            $table->integer('amount'); // in smallest currency unit
            $table->string('currency', 3)->default('PLN');
            $table->enum('status', ['completed', 'pending', 'refunded', 'failed'])->default('completed');

            // Additional data
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stripe_transactions');
    }
};
