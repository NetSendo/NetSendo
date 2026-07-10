<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brain 2.0 Phase 2 — unified revenue ledger.
 *
 * Normalizes completed payments from all platform silos (Stripe, Polar,
 * Tpay, WooCommerce webhook, purchase webhook, funnel goal conversions)
 * into one table so Brain can attribute revenue to campaigns and optimize
 * for RPM instead of open rates.
 *
 * Amounts are stored in MINOR units (grosze/cents) with an explicit
 * currency — provider defaults differ (Polar=USD, others=PLN).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('source', 30); // stripe|polar|tpay|woocommerce|purchase_webhook|funnel_goal
            $table->string('source_id', 191); // unique reference within source
            $table->foreignId('subscriber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email')->nullable()->index();
            $table->bigInteger('amount'); // minor units (grosze/cents)
            $table->char('currency', 3)->default('PLN');
            $table->timestamp('occurred_at')->index();

            // Attribution (Brain 2.0)
            $table->foreignId('attributed_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->unsignedBigInteger('attributed_funnel_id')->nullable();
            $table->string('attribution_type', 20)->nullable(); // last_click|funnel|none
            $table->timestamp('attributed_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_id']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'attributed_message_id']);
            $table->index(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_events');
    }
};
