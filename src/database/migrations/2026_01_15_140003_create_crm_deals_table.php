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
        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->date('expected_close_date')->nullable();
            $table->date('closed_at')->nullable();
            $table->enum('status', ['open', 'won', 'lost'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('crm_pipeline_id');
            $table->index('crm_stage_id');
            $table->index('crm_contact_id');
            $table->index('crm_company_id');
            $table->index('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
    }
};
