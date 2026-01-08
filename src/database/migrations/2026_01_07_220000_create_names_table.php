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
        Schema::create('names', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();
            $table->enum('gender', ['male', 'female', 'neutral'])->default('neutral');
            $table->string('country', 2)->default('PL')->index(); // ISO country code
            $table->enum('source', ['system', 'user'])->default('system');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();

            // Unique constraint per name + country + user
            $table->unique(['name', 'country', 'user_id'], 'names_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('names');
    }
};
