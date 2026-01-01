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
        Schema::table('webinars', function (Blueprint $table) {
            // List for people who clicked on webinar link (entered watch page)
            $table->foreignId('clicked_list_id')
                ->nullable()
                ->after('target_list_id')
                ->constrained('contact_lists')
                ->onDelete('set null');

            // List for people who attended above minimum time threshold
            $table->foreignId('attended_list_id')
                ->nullable()
                ->after('clicked_list_id')
                ->constrained('contact_lists')
                ->onDelete('set null');

            // Minimum minutes to be considered as "attended"
            $table->unsignedInteger('attended_min_minutes')
                ->default(5)
                ->after('attended_list_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webinars', function (Blueprint $table) {
            $table->dropForeign(['clicked_list_id']);
            $table->dropForeign(['attended_list_id']);
            $table->dropColumn(['clicked_list_id', 'attended_list_id', 'attended_min_minutes']);
        });
    }
};
