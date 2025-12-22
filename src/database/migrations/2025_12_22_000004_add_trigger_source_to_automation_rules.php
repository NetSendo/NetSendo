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
        Schema::table('automation_rules', function (Blueprint $table) {
            // Track where this automation was created from
            if (!Schema::hasColumn('automation_rules', 'trigger_source')) {
                $table->string('trigger_source')->nullable()->after('user_id'); // 'message', 'funnel', 'manual'
            }
            if (!Schema::hasColumn('automation_rules', 'trigger_source_id')) {
                $table->unsignedBigInteger('trigger_source_id')->nullable()->after('trigger_source');
            }
        });
        
        // Add index separately to handle case where it may already exist
        try {
            Schema::table('automation_rules', function (Blueprint $table) {
                $table->index(['trigger_source', 'trigger_source_id'], 'automation_rules_source_index');
            });
        } catch (\Exception $e) {
            // Index may already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropIndex('automation_rules_source_index');
            $table->dropColumn(['trigger_source', 'trigger_source_id']);
        });
    }
};
