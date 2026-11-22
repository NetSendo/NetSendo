<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An individual email: one recipient, one sender, sent through
     * POST /api/v1/email/send. It goes from the mailbox it was queued with or not
     * at all, and a mailbox that allows autoresponders may send it, as Gmail
     * mailboxes do. Older messages stay broadcasts (false).
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'is_individual')) {
                $table->boolean('is_individual')->default(false)->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'is_individual')) {
                $table->dropColumn('is_individual');
            }
        });
    }
};
