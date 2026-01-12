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
        Schema::create('plugin_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_type', 20); // 'wordpress' | 'woocommerce'
            $table->string('site_url', 500);
            $table->string('site_name', 255)->nullable();
            $table->string('plugin_version', 20);
            $table->string('wp_version', 20)->nullable();
            $table->string('wc_version', 20)->nullable(); // Only for WooCommerce plugins
            $table->string('php_version', 20)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('site_info')->nullable(); // Additional metadata
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Each user can only have one connection per plugin type per site
            $table->unique(['user_id', 'plugin_type', 'site_url'], 'plugin_connections_unique');
            $table->index(['user_id', 'plugin_type']);
            $table->index('last_heartbeat_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plugin_connections');
    }
};
