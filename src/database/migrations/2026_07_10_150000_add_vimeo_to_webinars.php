<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            if (!Schema::hasColumn('webinars', 'vimeo_id')) {
                // Vimeo video ID for live webinars (alternative to youtube_live_id).
                // May include the unlisted-video hash in the "123456789/abcdef123" form.
                $table->string('vimeo_id')->nullable()->after('youtube_live_id');
            }
        });

        // Backfill video_provider for existing YouTube webinars so the render
        // logic and provider selector default correctly.
        if (Schema::hasColumn('webinars', 'video_provider')) {
            DB::table('webinars')
                ->whereNull('video_provider')
                ->whereNotNull('youtube_live_id')
                ->update(['video_provider' => 'youtube']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            if (Schema::hasColumn('webinars', 'vimeo_id')) {
                $table->dropColumn('vimeo_id');
            }
        });
    }
};
