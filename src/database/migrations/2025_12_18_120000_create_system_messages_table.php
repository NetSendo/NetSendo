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
        Schema::create('system_messages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // e.g., 'signup_success'
            $table->string('name'); // e.g., 'Zapis poprawny' (Internal name)
            $table->string('title'); // e.g., 'Dziękujemy za zapisanie się' (Page Title)
            $table->longText('content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_messages');
    }
};
