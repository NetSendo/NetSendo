<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new step types to enum
        DB::statement("ALTER TABLE funnel_steps MODIFY COLUMN type ENUM('start', 'email', 'sms', 'delay', 'wait_until', 'condition', 'action', 'split', 'goal', 'end') DEFAULT 'email'");

        // Add new columns for enhanced step functionality
        Schema::table('funnel_steps', function (Blueprint $table) {
            // SMS step fields
            $table->text('sms_content')->nullable()->after('message_id');

            // Wait Until step fields
            $table->timestamp('wait_until_date')->nullable()->after('delay_unit');
            $table->time('wait_until_time')->nullable()->after('wait_until_date');
            $table->string('wait_until_timezone', 50)->nullable()->after('wait_until_time');
            $table->enum('wait_until_type', ['specific_date', 'day_of_week', 'business_hours'])->nullable()->after('wait_until_timezone');

            // Goal step fields
            $table->string('goal_name')->nullable()->after('action_config');
            $table->enum('goal_type', ['purchase', 'signup', 'page_visit', 'tag_added', 'custom', 'webhook'])->nullable()->after('goal_name');
            $table->decimal('goal_value', 10, 2)->nullable()->after('goal_type');
            $table->json('goal_config')->nullable()->after('goal_value');

            // Split (A/B) step fields
            $table->json('split_variants')->nullable()->after('goal_config');

            // Enhanced node styling
            $table->string('node_color', 20)->nullable()->after('position_y');
            $table->string('node_icon')->nullable()->after('node_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->dropColumn([
                'sms_content',
                'wait_until_date',
                'wait_until_time',
                'wait_until_timezone',
                'wait_until_type',
                'goal_name',
                'goal_type',
                'goal_value',
                'goal_config',
                'split_variants',
                'node_color',
                'node_icon',
            ]);
        });

        // Revert enum to original types
        DB::statement("ALTER TABLE funnel_steps MODIFY COLUMN type ENUM('start', 'email', 'delay', 'condition', 'action', 'end') DEFAULT 'email'");
    }
};
