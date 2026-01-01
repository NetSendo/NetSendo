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
        Schema::table('funnel_steps', function (Blueprint $table) {
            // Wait & Retry configuration
            $table->boolean('wait_for_condition')->default(false)->after('condition_config');
            $table->boolean('retry_enabled')->default(false)->after('wait_for_condition');
            $table->unsignedTinyInteger('retry_max_attempts')->default(3)->after('retry_enabled');
            $table->unsignedInteger('retry_interval_value')->default(24)->after('retry_max_attempts');
            $table->enum('retry_interval_unit', ['hours', 'days'])->default('hours')->after('retry_interval_value');
            $table->foreignId('retry_message_id')->nullable()->after('retry_interval_unit')
                ->constrained('messages')->nullOnDelete();
            $table->enum('retry_exhausted_action', ['continue', 'exit', 'unsubscribe'])
                ->default('continue')->after('retry_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->dropForeign(['retry_message_id']);
            $table->dropColumn([
                'wait_for_condition',
                'retry_enabled',
                'retry_max_attempts',
                'retry_interval_value',
                'retry_interval_unit',
                'retry_message_id',
                'retry_exhausted_action',
            ]);
        });
    }
};
