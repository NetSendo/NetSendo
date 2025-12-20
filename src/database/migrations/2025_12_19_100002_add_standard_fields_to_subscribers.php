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
        Schema::table('subscribers', function (Blueprint $table) {
            // Standard fields for all subscribers
            // Note: 'phone' column may already exist from SMS support migration
            if (!Schema::hasColumn('subscribers', 'phone')) {
                $table->string('phone', 50)->nullable()->after('last_name');
            }
            
            // Device detection fields (auto-filled during subscription)
            $table->string('device', 100)->nullable()->after('phone'); // Parsed device type
            $table->string('ip_address', 45)->nullable()->after('device'); // IPv4/IPv6
            $table->text('user_agent')->nullable()->after('ip_address'); // Original User-Agent
            
            // Subscription lifecycle
            $table->timestamp('subscribed_at')->nullable()->after('user_agent');
            $table->timestamp('confirmed_at')->nullable()->after('subscribed_at'); // Double opt-in confirmation
            
            // Engagement tracking
            $table->timestamp('last_opened_at')->nullable()->after('confirmed_at');
            $table->timestamp('last_clicked_at')->nullable()->after('last_opened_at');
            $table->unsignedInteger('opens_count')->default(0)->after('last_clicked_at');
            $table->unsignedInteger('clicks_count')->default(0)->after('opens_count');
            
            // Source tracking
            $table->string('source', 100)->nullable()->after('clicks_count'); // form, import, api
            
            // Tags for segmentation (JSON array)
            $table->json('tags')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $columns = [
                'device', 'ip_address', 'user_agent',
                'subscribed_at', 'confirmed_at',
                'last_opened_at', 'last_clicked_at',
                'opens_count', 'clicks_count',
                'source', 'tags'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('subscribers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
