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
            // Template relation
            $table->foreignId('template_id')
                ->nullable()
                ->after('mailbox_id')
                ->constrained('templates')
                ->nullOnDelete();

            // A/B Testing fields
            $table->boolean('ab_enabled')->default(false)->after('timezone');
            $table->string('ab_variant_subject')->nullable()->after('ab_enabled');
            $table->longText('ab_variant_content')->nullable()->after('ab_variant_subject');
            $table->unsignedTinyInteger('ab_split_percentage')->default(50)->after('ab_variant_content');

            // Trigger fields
            $table->string('trigger_type')->nullable()->after('ab_split_percentage');
            $table->json('trigger_config')->nullable()->after('trigger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn([
                'template_id',
                'ab_enabled',
                'ab_variant_subject',
                'ab_variant_content',
                'ab_split_percentage',
                'trigger_type',
                'trigger_config',
            ]);
        });
    }
};
