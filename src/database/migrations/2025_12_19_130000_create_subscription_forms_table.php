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
        Schema::create('subscription_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_list_id')->constrained()->onDelete('cascade');
            
            // Basic info
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->enum('status', ['active', 'draft', 'disabled'])->default('draft');
            $table->enum('type', ['inline', 'popup', 'embedded'])->default('inline');
            
            // Fields configuration
            $table->json('fields')->nullable(); // [{id, type, label, placeholder, required, order}]
            $table->json('custom_field_ids')->nullable(); // IDs of CustomField models used
            
            // Styling (comprehensive like old version)
            $table->json('styles')->nullable(); // All styling parameters
            $table->enum('layout', ['vertical', 'horizontal', 'grid'])->default('vertical');
            $table->enum('label_position', ['above', 'left', 'hidden'])->default('above');
            $table->boolean('show_placeholders')->default(true);
            
            // Functionality
            $table->boolean('double_optin')->nullable(); // null = inherit from list
            $table->boolean('require_policy')->default(false);
            $table->string('policy_url', 500)->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->text('success_message')->nullable();
            $table->text('error_message')->nullable();
            
            // Co-registration
            $table->json('coregister_lists')->nullable(); // [list_id, list_id, ...]
            $table->boolean('coregister_optional')->default(false);
            
            // Anti-spam
            $table->boolean('captcha_enabled')->default(false);
            $table->enum('captcha_provider', ['recaptcha_v2', 'recaptcha_v3', 'hcaptcha', 'turnstile'])->nullable();
            $table->string('captcha_site_key')->nullable();
            $table->text('captcha_secret_key')->nullable(); // Encrypted
            $table->boolean('honeypot_enabled')->default(true);
            
            // Stats cache
            $table->unsignedInteger('submissions_count')->default(0);
            $table->timestamp('last_submission_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index('contact_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_forms');
    }
};
