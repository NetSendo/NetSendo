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
        Schema::create('template_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_system']);
        });

        // Add category_id to templates
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained('template_categories')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('template_categories');
    }
};
