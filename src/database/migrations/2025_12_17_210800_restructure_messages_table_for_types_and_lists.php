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
            // Drop the old column if it exists
            $table->dropForeign(['contact_list_id']);
            $table->dropColumn('contact_list_id');

            // Add new columns
            $table->string('type')->default('broadcast')->after('user_id'); // broadcast, autoresponder
            $table->integer('day')->nullable()->after('type'); // for autoresponder (day 0, 1, 2...)
        });

        // Create pivot table for linking messages to multiple lists
        Schema::create('contact_list_message', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Prevent duplicate links
            $table->unique(['message_id', 'contact_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_list_message');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'day']);
            $table->foreignId('contact_list_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
