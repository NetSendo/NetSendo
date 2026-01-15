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
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete(); // 1:1 with Subscriber
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // Salesperson
            $table->foreignId('crm_company_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['lead', 'prospect', 'client', 'dormant', 'archived'])->default('lead');
            $table->string('source')->nullable(); // form, import, manual, linkedin, referral
            $table->integer('score')->default(0); // Lead scoring
            $table->string('position')->nullable(); // Job title
            $table->timestamps();
            $table->softDeletes();

            $table->unique('subscriber_id'); // Enforce 1:1
            $table->index(['user_id', 'status']);
            $table->index('owner_id');
            $table->index('crm_company_id');
            $table->index('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
