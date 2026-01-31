<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates pivot tables for CRM contact selection in messages:
     * - message_crm_contact: CRM contacts that will receive the message
     * - excluded_crm_contact_message: CRM contacts excluded from receiving
     */
    public function up(): void
    {
        // Create pivot table for target CRM contacts
        Schema::create('message_crm_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate selections
            $table->unique(['message_id', 'crm_contact_id'], 'message_crm_contact_unique');
        });

        // Create pivot table for excluded CRM contacts
        Schema::create('excluded_crm_contact_message', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate exclusions
            $table->unique(['message_id', 'crm_contact_id'], 'excluded_crm_contact_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excluded_crm_contact_message');
        Schema::dropIfExists('message_crm_contact');
    }
};
