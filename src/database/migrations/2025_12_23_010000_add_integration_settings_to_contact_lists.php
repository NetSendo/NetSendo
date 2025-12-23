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
            // Integration settings
            $table->string('api_key', 64)->nullable()->unique()->after('settings');
            $table->string('webhook_url')->nullable()->after('api_key');
            $table->json('webhook_events')->nullable()->after('webhook_url');
            
            // Co-registration / List collaboration
            $table->foreignId('parent_list_id')->nullable()->after('webhook_events')
                ->constrained('contact_lists')->nullOnDelete();
            $table->json('sync_settings')->nullable()->after('parent_list_id');
            
            // Advanced limits
            $table->unsignedInteger('max_subscribers')->default(0)->after('sync_settings');
            $table->boolean('signups_blocked')->default(false)->after('max_subscribers');
            $table->json('required_fields')->nullable()->after('signups_blocked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropForeign(['parent_list_id']);
            $table->dropColumn([
                'api_key',
                'webhook_url',
                'webhook_events',
                'parent_list_id',
                'sync_settings',
                'max_subscribers',
                'signups_blocked',
                'required_fields',
            ]);
        });
    }
};
