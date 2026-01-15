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
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('subject'); // crm_contact, crm_deal, crm_company
            $table->enum('type', [
                'note',
                'call',
                'email',
                'meeting',
                'task_completed',
                'stage_changed',
                'deal_created',
                'deal_won',
                'deal_lost',
                'contact_created',
                'system'
            ]);
            $table->text('content')->nullable();
            $table->json('metadata')->nullable(); // Extra data like old_stage, new_stage
            $table->timestamps();

            // Note: morphs() already creates index on subject_type, subject_id
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
