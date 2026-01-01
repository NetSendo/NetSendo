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
            $table->foreignId('webinar_id')->nullable()->after('template_id')->constrained()->nullOnDelete();
            $table->boolean('webinar_auto_register')->default(true)->after('webinar_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['webinar_id']);
            $table->dropColumn(['webinar_id', 'webinar_auto_register']);
        });
    }
};
