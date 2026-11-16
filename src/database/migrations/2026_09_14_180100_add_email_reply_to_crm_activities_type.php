<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'email_reply' (a reply read from the mailbox's reply inbox) to the enum type
        $types = [
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
            'system',
            'status_changed',
            'email_reply',
        ];

        if (DB::getDriverName() === 'mysql') {
            $list = implode(', ', array_map(fn ($type) => "'{$type}'", $types));
            DB::statement("ALTER TABLE crm_activities MODIFY COLUMN type ENUM({$list})");
        } elseif (DB::getDriverName() === 'sqlite') {
            // The earlier type migrations only widen the enum on MySQL, so on
            // SQLite (the test database) the CHECK constraint from the create
            // migration rejected 'status_changed' and 'email_reply'.
            Schema::table('crm_activities', function (Blueprint $table) use ($types) {
                $table->enum('type', $types)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversing: rows of type 'email_reply' would no longer fit the enum
    }
};
