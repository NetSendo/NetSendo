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
        Schema::create('dedicated_ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ip_pool_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_configuration_id')->nullable()->constrained()->onDelete('set null');

            // IP details
            $table->string('ip_address', 45); // Support IPv6
            $table->string('hostname')->nullable(); // e.g., mail1.netsendo.com
            $table->enum('ip_version', ['ipv4', 'ipv6'])->default('ipv4');

            // Provider info (for auto-provisioning)
            $table->string('provider')->nullable(); // vultr, linode, digitalocean
            $table->string('provider_id')->nullable(); // External ID from provider
            $table->string('provider_region')->nullable();

            // Reverse DNS
            $table->string('ptr_record')->nullable();
            $table->boolean('ptr_verified')->default(false);
            $table->timestamp('ptr_verified_at')->nullable();

            // Warming status
            $table->enum('warming_status', ['new', 'warming', 'warmed', 'paused'])->default('new');
            $table->timestamp('warming_started_at')->nullable();
            $table->timestamp('warming_completed_at')->nullable();
            $table->unsignedInteger('warming_day')->default(0);
            $table->unsignedInteger('warming_daily_limit')->nullable();

            // Sending statistics
            $table->unsignedBigInteger('total_sent')->default(0);
            $table->unsignedBigInteger('total_delivered')->default(0);
            $table->unsignedBigInteger('total_bounced')->default(0);
            $table->unsignedBigInteger('total_complaints')->default(0);
            $table->unsignedInteger('sent_today')->default(0);
            $table->date('sent_today_date')->nullable();

            // Reputation
            $table->decimal('reputation_score', 5, 2)->default(100.00); // 0-100
            $table->json('blacklist_status')->nullable(); // {spamhaus: false, spamcop: false, ...}
            $table->timestamp('blacklist_checked_at')->nullable();

            // DKIM for this IP/domain
            $table->string('dkim_selector', 63)->nullable();
            $table->text('dkim_private_key')->nullable(); // Encrypted
            $table->text('dkim_public_key')->nullable();
            $table->timestamp('dkim_generated_at')->nullable();
            $table->timestamp('dkim_rotated_at')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->string('status_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique('ip_address');
            $table->index('warming_status');
            $table->index(['ip_pool_id', 'is_active']);
            $table->index('domain_configuration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dedicated_ip_addresses');
    }
};
