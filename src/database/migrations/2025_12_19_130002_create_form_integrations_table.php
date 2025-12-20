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
        Schema::create('form_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_form_id')->constrained()->onDelete('cascade');
            
            // Integration type
            $table->enum('type', ['webhook', 'wordpress', 'elementor', 'zapier', 'make', 'n8n'])->default('webhook');
            $table->string('name');
            $table->enum('status', ['active', 'disabled', 'error'])->default('active');
            
            // Webhook configuration
            $table->string('webhook_url', 500)->nullable();
            $table->enum('webhook_method', ['POST', 'GET'])->default('POST');
            $table->json('webhook_headers')->nullable(); // Custom headers
            $table->enum('webhook_format', ['json', 'form'])->default('json');
            
            // Authentication
            $table->enum('auth_type', ['none', 'bearer', 'basic', 'api_key'])->default('none');
            $table->text('auth_token')->nullable(); // Encrypted
            $table->string('api_key_name', 50)->nullable(); // e.g. 'X-API-Key'
            $table->text('api_key_value')->nullable(); // Encrypted
            
            // Triggers
            $table->json('trigger_on')->nullable(); // ['submission', 'confirmation', 'error']
            
            // Stats
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('last_error')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['subscription_form_id', 'status']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_integrations');
    }
};
