<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fixes: Zoom start_url contains JWT tokens that can exceed 600+ characters.
     * Changed zoom_meeting_link (start_url) from VARCHAR(500) to TEXT.
     */
    public function up(): void
    {
        // Use raw SQL for MySQL to change column type without dropping
        DB::statement('ALTER TABLE crm_tasks MODIFY zoom_meeting_link TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This might truncate data if URLs are longer than 500 chars
        DB::statement('ALTER TABLE crm_tasks MODIFY zoom_meeting_link VARCHAR(500) NULL');
    }
};
