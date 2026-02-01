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
        // 1. Business Card Scans
        Schema::create('cardintel_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path'); // Storage path
            $table->string('file_url')->nullable(); // Public URL
            $table->text('raw_text')->nullable(); // OCR raw text output
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('mode', ['manual', 'agent', 'auto'])->default('manual');
            $table->string('error_message')->nullable(); // Error if failed
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        // 2. OCR Extractions
        Schema::create('cardintel_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('cardintel_scans')->cascadeOnDelete();
            $table->json('fields_json'); // {first_name, last_name, company, email, phone, website, nip, regon, position}
            $table->json('confidence_json'); // {field_name: 0.0-1.0}
            $table->json('normalized_json')->nullable(); // Cleaned/normalized fields
            $table->timestamps();

            $table->unique('scan_id');
        });

        // 3. Context Scoring
        Schema::create('cardintel_context', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('cardintel_scans')->cascadeOnDelete();
            $table->enum('context_level', ['LOW', 'MEDIUM', 'HIGH'])->default('LOW');
            $table->unsignedTinyInteger('quality_score')->default(0); // 0-100
            $table->json('signals_json'); // {has_website: true, corporate_email: false, ...}
            $table->text('reasoning_short')->nullable(); // Max 6 bullet points explaining the score
            $table->timestamps();

            $table->unique('scan_id');
            $table->index('context_level');
            $table->index('quality_score');
        });

        // 4. Enrichment Data
        Schema::create('cardintel_enrichment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('cardintel_scans')->cascadeOnDelete();
            $table->text('website_summary')->nullable(); // AI-generated business summary
            $table->json('firmographics_json')->nullable(); // {industry, size, nip_verified, regon, address, ...}
            $table->string('language', 10)->nullable(); // pl, en, de, etc.
            $table->enum('b2b_b2c_guess', ['B2B', 'B2C', 'MIXED', 'UNKNOWN'])->nullable();
            $table->text('use_case_hypothesis')->nullable(); // AI-generated use case
            $table->timestamps();

            $table->unique('scan_id');
        });

        // 5. Action History
        Schema::create('cardintel_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('cardintel_scans')->cascadeOnDelete();
            $table->enum('action_type', [
                'save_memory',
                'add_crm',
                'add_email_list',
                'add_sms_list',
                'send_email',
                'send_sms',
                'generate_message'
            ]);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->json('payload_json')->nullable(); // Action-specific data (list_id, message content, etc.)
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['scan_id', 'action_type']);
            $table->index('status');
        });

        // 6. Contact Intelligence Records (NetSendo Memory)
        Schema::create('contact_intelligence_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('contact_key')->index(); // Email hash or phone hash as unique identifier
            $table->foreignId('latest_scan_id')->nullable()->constrained('cardintel_scans')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->json('merged_profile_json'); // Combined profile from all scans
            $table->json('timeline_json')->nullable(); // History of interactions and decisions
            $table->boolean('is_synced_to_crm')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'contact_key']);
            $table->index('is_synced_to_crm');
        });

        // 7. User Settings for CardIntel
        Schema::create('cardintel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Mode settings
            $table->enum('default_mode', ['manual', 'agent', 'auto'])->default('manual');

            // Scoring thresholds
            $table->unsignedTinyInteger('low_threshold')->default(50); // Below this = LOW
            $table->unsignedTinyInteger('high_threshold')->default(80); // Above this = HIGH

            // CRM integration
            $table->enum('crm_sync_mode', ['always', 'approve', 'high_only', 'never'])->default('approve');
            $table->unsignedTinyInteger('crm_min_score')->default(80); // For high_only mode

            // List integration
            $table->json('default_email_lists')->nullable(); // Array of list IDs
            $table->json('default_sms_lists')->nullable(); // Array of list IDs
            $table->enum('list_add_mode', ['always', 'approve', 'high_only'])->default('approve');

            // Enrichment settings
            $table->boolean('enrichment_enabled')->default(true);
            $table->boolean('enrichment_only_medium_high')->default(true);
            $table->unsignedSmallInteger('enrichment_timeout')->default(10); // seconds

            // Auto mode guardrails
            $table->boolean('auto_send_enabled')->default(false);
            $table->unsignedTinyInteger('auto_send_min_score')->default(80);
            $table->boolean('auto_send_corporate_only')->default(true);

            // Message preferences
            $table->string('default_tone', 50)->default('professional'); // professional, friendly, formal
            $table->boolean('show_all_context_levels')->default(true); // Show LOW/MEDIUM/HIGH drafts

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cardintel_settings');
        Schema::dropIfExists('contact_intelligence_records');
        Schema::dropIfExists('cardintel_actions');
        Schema::dropIfExists('cardintel_enrichment');
        Schema::dropIfExists('cardintel_context');
        Schema::dropIfExists('cardintel_extractions');
        Schema::dropIfExists('cardintel_scans');
    }
};
