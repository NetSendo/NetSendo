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
            // Status aktywności dla wiadomości typu Kolejka (autoresponder)
            if (!Schema::hasColumn('messages', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('status');
            }
            
            // Rzeczywista liczba wysłanych wiadomości
            if (!Schema::hasColumn('messages', 'sent_count')) {
                $table->unsignedInteger('sent_count')->default(0)->after('is_active');
            }
            
            // Liczba planowanych odbiorców (przeliczona przez CRON)
            if (!Schema::hasColumn('messages', 'planned_recipients_count')) {
                $table->unsignedInteger('planned_recipients_count')->nullable()->after('sent_count');
            }
            
            // Timestamp kiedy przeliczono odbiorców
            if (!Schema::hasColumn('messages', 'recipients_calculated_at')) {
                $table->timestamp('recipients_calculated_at')->nullable()->after('planned_recipients_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $columns = ['is_active', 'sent_count', 'planned_recipients_count', 'recipients_calculated_at'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
