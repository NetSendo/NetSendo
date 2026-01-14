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
        // Media folders for organization
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();

            $table->index(['user_id', 'parent_id']);
        });

        // Brands for brand management
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->string('primary_color', 7)->nullable(); // HEX color
            $table->string('secondary_color', 7)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        // Main media table
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->onDelete('set null');
            $table->string('original_name');
            $table->string('stored_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->enum('type', ['image', 'logo', 'icon', 'document'])->default('image');
            $table->string('alt_text')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'brand_id']);
            $table->index(['user_id', 'folder_id']);
        });

        // Add foreign key for brand logo after media table exists
        Schema::table('brands', function (Blueprint $table) {
            $table->foreign('logo_media_id')->references('id')->on('media')->onDelete('set null');
        });

        // Colors extracted from media
        Schema::create('media_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained()->onDelete('cascade');
            $table->string('hex_color', 7);
            $table->unsignedTinyInteger('rgb_r');
            $table->unsignedTinyInteger('rgb_g');
            $table->unsignedTinyInteger('rgb_b');
            $table->unsignedInteger('population')->default(0); // Pixel count
            $table->boolean('is_dominant')->default(false);
            $table->unsignedTinyInteger('position')->default(0); // Order (1=most important)
            $table->timestamp('created_at')->useCurrent();

            $table->index('media_id');
        });

        // Brand color palettes
        Schema::create('brand_palettes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->json('colors'); // Array of HEX colors
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign(['logo_media_id']);
        });

        Schema::dropIfExists('brand_palettes');
        Schema::dropIfExists('media_colors');
        Schema::dropIfExists('media');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('media_folders');
    }
};
