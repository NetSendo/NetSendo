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
        Schema::table('messages', function (Blueprint $table) {
            // Channel type (email, sms)
            if (!Schema::hasColumn('messages', 'channel')) {
                $table->string('channel')->default('email')->after('user_id');
            }
            
            // Timezone for scheduling
            if (!Schema::hasColumn('messages', 'timezone')) {
                $table->string('timezone')->nullable()->after('status');
            }
            
            // Send at datetime
            if (!Schema::hasColumn('messages', 'send_at')) {
                $table->timestamp('send_at')->nullable()->after('timezone');
            }
            
            // Time of day for recurring messages
            if (!Schema::hasColumn('messages', 'time_of_day')) {
                $table->string('time_of_day')->nullable()->after('send_at');
            }
            
            // Scheduled at - used by CRON for queue processing
            if (!Schema::hasColumn('messages', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('time_of_day')->index();
            }
            
            // Priority for queue ordering
            if (!Schema::hasColumn('messages', 'priority')) {
                $table->unsignedTinyInteger('priority')->default(0)->after('scheduled_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $columns = ['channel', 'timezone', 'send_at', 'time_of_day', 'scheduled_at', 'priority'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
