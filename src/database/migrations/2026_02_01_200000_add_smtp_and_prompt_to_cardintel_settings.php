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
        Schema::table('cardintel_settings', function (Blueprint $table) {
            $table->foreignId('default_mailbox_id')
                ->nullable()
                ->after('default_tone')
                ->constrained('mailboxes')
                ->nullOnDelete();

            $table->text('custom_ai_prompt')
                ->nullable()
                ->after('default_mailbox_id');

            $table->string('allowed_html_tags', 500)
                ->nullable()
                ->default('p,br,strong,em,ul,ol,li,a,h3,h4')
                ->after('custom_ai_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cardintel_settings', function (Blueprint $table) {
            $table->dropForeign(['default_mailbox_id']);
            $table->dropColumn(['default_mailbox_id', 'custom_ai_prompt', 'allowed_html_tags']);
        });
    }
};
