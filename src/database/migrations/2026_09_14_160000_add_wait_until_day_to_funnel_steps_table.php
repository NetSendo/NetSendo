<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "wait until" step of type `day_of_week` had no column for the day, so it
     * could not be configured. ISO weekday: 1 = Monday ... 7 = Sunday.
     */
    public function up(): void
    {
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->unsignedTinyInteger('wait_until_day')->nullable()->after('wait_until_time');
        });

        // SQLite stores an enum as a CHECK constraint, which only a table rebuild
        // changes. The SMS, wait-until, split and goal step types were added on
        // MySQL alone (2026_01_16_000001_add_enhanced_step_types_to_funnel_steps_table)
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('funnel_steps', function (Blueprint $table) {
                $table->enum('type', ['start', 'email', 'sms', 'delay', 'wait_until', 'condition', 'action', 'split', 'goal', 'end'])
                    ->default('email')
                    ->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funnel_steps', function (Blueprint $table) {
            $table->dropColumn('wait_until_day');
        });
    }
};
