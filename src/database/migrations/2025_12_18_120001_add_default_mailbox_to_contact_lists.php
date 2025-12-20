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
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->foreignId('default_mailbox_id')
                ->nullable()
                ->after('contact_list_group_id')
                ->constrained('mailboxes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropForeign(['default_mailbox_id']);
            $table->dropColumn('default_mailbox_id');
        });
    }
};
