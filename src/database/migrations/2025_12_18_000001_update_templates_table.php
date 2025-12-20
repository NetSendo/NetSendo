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
        Schema::table('templates', function (Blueprint $table) {
            $table->string('category')->nullable()->after('thumbnail');
            $table->longText('mjml_content')->nullable()->after('content');
            $table->json('json_structure')->nullable()->after('mjml_content');
            $table->string('preheader', 500)->nullable()->after('name');
            $table->json('settings')->nullable()->after('json_structure');
            $table->boolean('is_public')->default(false)->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'mjml_content',
                'json_structure',
                'preheader',
                'settings',
                'is_public',
            ]);
        });
    }
};
