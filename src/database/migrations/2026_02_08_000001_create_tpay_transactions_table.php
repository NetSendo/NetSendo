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
        Schema::create('tpay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tpay_product_id')->nullable()->constrained('tpay_products')->onDelete('set null');
            $table->foreignId('subscriber_id')->nullable()->constrained('subscribers')->onDelete('set null');

            // Tpay transaction identifiers
            $table->string('tpay_transaction_id')->nullable()->unique();
            $table->string('tpay_title')->nullable();

            // Customer details
            $table->string('customer_email');
            $table->string('customer_name')->nullable();

            // Payment details
            $table->integer('amount'); // In grosze (1/100 PLN)
            $table->string('currency', 3)->default('PLN');
            $table->enum('status', ['pending', 'completed', 'refunded', 'failed', 'chargeback'])->default('pending');
            $table->string('payment_method')->nullable(); // transfer, blik, card, etc.

            // Verification
            $table->string('tr_crc')->nullable(); // For md5sum verification

            // Raw notification data
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('customer_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tpay_transactions');
    }
};
