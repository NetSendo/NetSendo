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
        // Add variant tracking to email_opens
        Schema::table('email_opens', function (Blueprint $table) {
            $table->foreignId('ab_test_variant_id')
                ->nullable()
                ->after('subscriber_id')
                ->constrained('ab_test_variants')
                ->nullOnDelete();
        });

        // Add variant tracking to email_clicks
        Schema::table('email_clicks', function (Blueprint $table) {
            $table->foreignId('ab_test_variant_id')
                ->nullable()
                ->after('subscriber_id')
                ->constrained('ab_test_variants')
                ->nullOnDelete();
        });

        // Add variant tracking to message_queue_entries
        Schema::table('message_queue_entries', function (Blueprint $table) {
            $table->foreignId('ab_test_variant_id')
                ->nullable()
                ->after('status')
                ->constrained('ab_test_variants')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_opens', function (Blueprint $table) {
            $table->dropForeign(['ab_test_variant_id']);
            $table->dropColumn('ab_test_variant_id');
        });

        Schema::table('email_clicks', function (Blueprint $table) {
            $table->dropForeign(['ab_test_variant_id']);
            $table->dropColumn('ab_test_variant_id');
        });

        Schema::table('message_queue_entries', function (Blueprint $table) {
            $table->dropForeign(['ab_test_variant_id']);
            $table->dropColumn('ab_test_variant_id');
        });
    }
};
