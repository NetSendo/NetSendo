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
        Schema::create('email_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('opened_at');
            $table->timestamps();

            $table->index(['message_id', 'subscriber_id']);
        });

        Schema::create('email_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->index(['message_id', 'subscriber_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_clicks');
        Schema::dropIfExists('email_opens');
    }
};
