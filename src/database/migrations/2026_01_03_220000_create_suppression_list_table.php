<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Suppression list stores emails of users who exercised their GDPR "right to be forgotten".
     * This is the ONLY data we keep to prevent accidentally re-adding them to mailing lists.
     */
    public function up(): void
    {
        Schema::create('suppression_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('email')->index();
            $table->string('reason')->default('gdpr_erasure'); // gdpr_erasure, spam_complaint, bounce, etc.
            $table->timestamp('suppressed_at');
            $table->timestamps();

            // Unique constraint per user
            $table->unique(['user_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppression_list');
    }
};
