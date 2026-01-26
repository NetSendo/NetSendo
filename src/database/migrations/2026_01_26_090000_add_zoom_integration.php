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
        // Create user_zoom_connections table
        Schema::create('user_zoom_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('zoom_user_id')->nullable();
            $table->string('zoom_email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });

        // Add Zoom fields to crm_tasks
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->string('zoom_meeting_id')->nullable()->after('attendees_data');
            $table->string('zoom_meeting_link', 500)->nullable()->after('zoom_meeting_id');
            $table->string('zoom_join_url', 500)->nullable()->after('zoom_meeting_link');
            $table->boolean('include_zoom_meeting')->default(false)->after('zoom_join_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'zoom_meeting_id',
                'zoom_meeting_link',
                'zoom_join_url',
                'include_zoom_meeting',
            ]);
        });

        Schema::dropIfExists('user_zoom_connections');
    }
};
