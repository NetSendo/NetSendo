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
        // Add default SMS provider to contact lists
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->foreignId('default_sms_provider_id')
                ->nullable()
                ->after('default_mailbox_id')
                ->constrained('sms_providers')
                ->nullOnDelete();
        });

        // Add SMS provider to messages (for SMS channel)
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sms_provider_id')
                ->nullable()
                ->after('mailbox_id')
                ->constrained('sms_providers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['sms_provider_id']);
            $table->dropColumn('sms_provider_id');
        });

        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropForeign(['default_sms_provider_id']);
            $table->dropColumn('default_sms_provider_id');
        });
    }
};
