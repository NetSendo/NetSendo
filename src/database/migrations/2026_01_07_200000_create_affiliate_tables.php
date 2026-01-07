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
        // Core Tables (Silver)

        // Affiliate Programs - Owner's program configuration
        if (!Schema::hasTable('affiliate_programs')) {
            Schema::create('affiliate_programs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('name');
                $table->string('slug')->unique();
                $table->enum('status', ['active', 'paused', 'closed'])->default('active');
                $table->text('terms_text')->nullable();
                $table->string('terms_url')->nullable();
                $table->integer('cookie_days')->default(30);
                $table->enum('attribution_model', ['last_click', 'first_click', 'linear'])->default('last_click');
                $table->string('currency', 3)->default('PLN');
                $table->decimal('default_commission_percent', 5, 2)->default(10.00);
                $table->decimal('default_commission_fixed', 10, 2)->nullable();
                $table->boolean('auto_approve_affiliates')->default(false);
                $table->integer('max_levels')->default(2); // Silver: max 2, Gold: unlimited
                $table->json('settings')->nullable(); // Additional settings
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }

        // Affiliate Offers - Products/Funnels linked to program
        if (!Schema::hasTable('affiliate_offers')) {
            Schema::create('affiliate_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('affiliate_programs')->onDelete('cascade');
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('type', ['funnel', 'landing', 'stripe_product', 'polar_product', 'external'])->default('funnel');
                $table->unsignedBigInteger('entity_id')->nullable(); // Reference to actual entity
                $table->string('external_url')->nullable(); // For external offers
                $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
                $table->decimal('commission_value', 10, 2)->default(10.00);
                $table->boolean('is_public')->default(true); // Visible in affiliate marketplace
                $table->boolean('is_active')->default(true);
                $table->string('image_url')->nullable();
                $table->json('meta')->nullable(); // Additional metadata
                $table->timestamps();

                $table->index(['program_id', 'is_active', 'is_public']);
                $table->index(['type', 'entity_id']);
            });
        }

        // Affiliates - Partner accounts
        if (!Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('affiliate_programs')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Optional link to NetSendo user
                $table->unsignedBigInteger('parent_affiliate_id')->nullable(); // For multi-level
                $table->string('email')->index();
                $table->string('name');
                $table->string('password'); // Hashed password for portal login
                $table->enum('status', ['pending', 'approved', 'blocked'])->default('pending');
                $table->string('company_name')->nullable();
                $table->string('country', 2)->nullable();
                $table->enum('payout_method', ['manual', 'paypal', 'bank', 'stripe'])->default('manual');
                $table->json('payout_details')->nullable(); // PayPal email, bank details, etc.
                $table->string('referral_code')->unique(); // Unique affiliate code
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('parent_affiliate_id')->references('id')->on('affiliates')->onDelete('set null');
                $table->index(['program_id', 'status']);
                $table->unique(['program_id', 'email']);
            });
        }

        // Affiliate Links - Tracking links per affiliate per offer
        if (!Schema::hasTable('affiliate_links')) {
            Schema::create('affiliate_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('offer_id')->constrained('affiliate_offers')->onDelete('cascade');
                $table->string('code')->unique(); // Unique tracking code (e.g., AFF123XYZ)
                $table->json('utm_defaults')->nullable(); // Default UTM parameters
                $table->string('custom_slug')->nullable(); // Optional custom URL slug
                $table->unsignedInteger('clicks_count')->default(0); // Denormalized for performance
                $table->timestamps();

                $table->index(['affiliate_id', 'offer_id']);
            });
        }

        // Affiliate Coupons - Discount codes linked to affiliates
        if (!Schema::hasTable('affiliate_coupons')) {
            Schema::create('affiliate_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('offer_id')->nullable()->constrained('affiliate_offers')->onDelete('cascade'); // Null = global
                $table->string('code')->unique();
                $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $table->decimal('discount_value', 10, 2)->default(10.00);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedInteger('usage_limit')->nullable();
                $table->unsignedInteger('usage_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['affiliate_id', 'is_active']);
                $table->index(['code', 'is_active']);
            });
        }

        // Affiliate Clicks - Click tracking records
        if (!Schema::hasTable('affiliate_clicks')) {
            Schema::create('affiliate_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->nullable()->constrained('affiliate_links')->onDelete('set null');
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('offer_id')->constrained('affiliate_offers')->onDelete('cascade');
                $table->string('ip_hash', 64)->nullable(); // Hashed IP for privacy
                $table->string('ua_hash', 64)->nullable(); // Hashed User Agent
                $table->string('referrer', 500)->nullable();
                $table->string('landing_url', 500)->nullable();
                $table->string('session_id', 64)->nullable();
                $table->string('cookie_id', 64)->nullable(); // For cookie-based tracking
                $table->json('utm_data')->nullable(); // Captured UTM params
                $table->boolean('is_unique')->default(true); // First click from this visitor
                $table->timestamp('created_at')->useCurrent();

                $table->index(['affiliate_id', 'created_at']);
                $table->index(['offer_id', 'created_at']);
                $table->index(['cookie_id']);
                $table->index(['ip_hash', 'created_at']);
            });
        }

        // Affiliate Conversions - Lead and purchase events
        if (!Schema::hasTable('affiliate_conversions')) {
            Schema::create('affiliate_conversions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('offer_id')->constrained('affiliate_offers')->onDelete('cascade');
                $table->foreignId('click_id')->nullable()->constrained('affiliate_clicks')->onDelete('set null');
                $table->enum('type', ['lead', 'purchase', 'refund'])->default('purchase');
                $table->string('entity_type')->nullable(); // e.g., 'subscriber', 'stripe_transaction'
                $table->unsignedBigInteger('entity_id')->nullable(); // Reference to the entity
                $table->decimal('amount', 12, 2)->default(0.00); // Transaction amount
                $table->string('currency', 3)->default('PLN');
                $table->string('customer_email')->nullable();
                $table->string('customer_name')->nullable();
                $table->string('order_id')->nullable(); // External order reference
                $table->json('meta')->nullable(); // Additional data
                $table->timestamp('created_at')->useCurrent();

                $table->index(['affiliate_id', 'type', 'created_at']);
                $table->index(['offer_id', 'type', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
            });
        }

        // Affiliate Payouts - Batch payout records (MUST come before commissions due to FK)
        if (!Schema::hasTable('affiliate_payouts')) {
            Schema::create('affiliate_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('affiliate_programs')->onDelete('cascade');
                $table->foreignId('affiliate_id')->nullable()->constrained()->onDelete('cascade'); // Null = batch for all
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('total_amount', 12, 2);
                $table->string('currency', 3)->default('PLN');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->string('payment_reference')->nullable(); // PayPal transaction ID, bank reference, etc.
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['program_id', 'status']);
                $table->index(['affiliate_id', 'status']);
            });
        }

        // Affiliate Commissions - Commission ledger
        if (!Schema::hasTable('affiliate_commissions')) {
            Schema::create('affiliate_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversion_id')->constrained('affiliate_conversions')->onDelete('cascade');
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('offer_id')->constrained('affiliate_offers')->onDelete('cascade');
                $table->unsignedTinyInteger('level')->default(1); // Commission level (1 = direct, 2 = second tier, etc.)
                $table->decimal('commission_amount', 12, 2);
                $table->string('currency', 3)->default('PLN');
                $table->enum('status', ['pending', 'approved', 'payable', 'paid', 'rejected', 'reversed'])->default('pending');
                $table->timestamp('available_at')->nullable(); // When commission becomes payable
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('payout_id')->nullable()->constrained('affiliate_payouts')->onDelete('set null');
                $table->string('rejection_reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['affiliate_id', 'status']);
                $table->index(['status', 'available_at']);
                $table->index(['payout_id']);
            });
        }

        // Affiliate Payout Items - Individual commissions in a payout
        if (!Schema::hasTable('affiliate_payout_items')) {
            Schema::create('affiliate_payout_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payout_id')->constrained('affiliate_payouts')->onDelete('cascade');
                $table->foreignId('commission_id')->constrained('affiliate_commissions')->onDelete('cascade');
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->unique(['payout_id', 'commission_id']);
            });
        }

        // Gold-only Tables

        // Affiliate Level Rules - Multi-level commission rules
        if (!Schema::hasTable('affiliate_level_rules')) {
            Schema::create('affiliate_level_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('affiliate_programs')->onDelete('cascade');
                $table->unsignedTinyInteger('level'); // 1, 2, 3, ... N
                $table->enum('commission_type', ['percent', 'fixed'])->default('percent');
                $table->decimal('commission_value', 10, 2);
                $table->unsignedInteger('min_sales_required')->default(0); // Minimum sales to unlock this level
                $table->json('conditions')->nullable(); // Additional conditions
                $table->timestamps();

                $table->unique(['program_id', 'level']);
            });
        }

        // Affiliate Attribution Rules - Custom attribution settings
        if (!Schema::hasTable('affiliate_attribution_rules')) {
            Schema::create('affiliate_attribution_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('affiliate_programs')->onDelete('cascade');
                $table->enum('model', ['first_click', 'last_click', 'linear', 'time_decay'])->default('last_click');
                $table->unsignedInteger('window_days')->default(30);
                $table->boolean('cross_device_tracking')->default(false);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->unique(['program_id']);
            });
        }

        // Affiliate Fraud Flags - Anti-fraud tracking
        if (!Schema::hasTable('affiliate_fraud_flags')) {
            Schema::create('affiliate_fraud_flags', function (Blueprint $table) {
                $table->id();
                $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
                $table->foreignId('click_id')->nullable()->constrained('affiliate_clicks')->onDelete('cascade');
                $table->foreignId('conversion_id')->nullable()->constrained('affiliate_conversions')->onDelete('cascade');
                $table->enum('type', ['duplicate_ip', 'suspicious_pattern', 'self_referral', 'rapid_clicks', 'vpn_detected', 'other'])->default('other');
                $table->string('reason');
                $table->unsignedTinyInteger('severity')->default(1); // 1-10 score
                $table->boolean('is_reviewed')->default(false);
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['affiliate_id', 'is_reviewed']);
                $table->index(['type', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_fraud_flags');
        Schema::dropIfExists('affiliate_attribution_rules');
        Schema::dropIfExists('affiliate_level_rules');
        Schema::dropIfExists('affiliate_payout_items');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_conversions');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_coupons');
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('affiliate_offers');
        Schema::dropIfExists('affiliate_programs');
    }
};
