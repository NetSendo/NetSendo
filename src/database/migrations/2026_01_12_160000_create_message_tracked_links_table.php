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
        Schema::create('message_tracked_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->string('url', 2048); // Original URL from message content
            $table->string('url_hash', 64)->index(); // SHA256 hash for faster lookups
            $table->boolean('tracking_enabled')->default(true);
            $table->boolean('share_data_enabled')->default(false);
            $table->json('shared_fields')->nullable(); // Array of field names to share: ['fname', 'lname', 'email', ...]
            $table->json('subscribe_to_list_ids')->nullable(); // Array of list IDs to subscribe on click
            $table->json('unsubscribe_from_list_ids')->nullable(); // Array of list IDs to unsubscribe on click
            $table->timestamps();

            // Compound index for message + URL hash lookups
            $table->unique(['message_id', 'url_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_tracked_links');
    }
};
