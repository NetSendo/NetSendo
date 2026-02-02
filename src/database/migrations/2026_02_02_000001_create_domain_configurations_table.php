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
        Schema::create('domain_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('domain')->index();

            // CNAME verification
            $table->string('cname_selector')->unique(); // Unique selector for this domain
            $table->boolean('cname_verified')->default(false);
            $table->timestamp('cname_verified_at')->nullable();

            // DNS status (pending, valid, warning, critical)
            $table->enum('spf_status', ['pending', 'valid', 'warning', 'critical'])->default('pending');
            $table->enum('dkim_status', ['pending', 'valid', 'warning', 'critical'])->default('pending');
            $table->enum('dmarc_status', ['pending', 'valid', 'warning', 'critical'])->default('pending');

            // DMARC policy progression (none -> quarantine -> reject)
            $table->enum('dmarc_policy', ['none', 'quarantine', 'reject'])->default('none');
            $table->timestamp('dmarc_upgraded_at')->nullable();

            // Overall status for quick filtering
            $table->enum('overall_status', ['pending', 'safe', 'warning', 'critical'])->default('pending');

            // Monitoring schedule
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->integer('consecutive_failures')->default(0);

            // Detailed DNS records (cached)
            $table->json('dns_records')->nullable(); // SPF, DKIM, DMARC raw records
            $table->json('check_history')->nullable(); // Last N check results

            // Alert settings
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamp('last_alert_at')->nullable();

            // Integration with mailboxes
            $table->foreignId('mailbox_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['user_id', 'domain']);
            $table->index('overall_status');
            $table->index('next_check_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_configurations');
    }
};
