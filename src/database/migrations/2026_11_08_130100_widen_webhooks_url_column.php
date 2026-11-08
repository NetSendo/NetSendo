<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The API accepts webhook URLs of up to 2,048 characters, but the column was VARCHAR(255),
     * so a longer URL (e.g. with a signed query string) ended in a 500 on MySQL.
     */
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('url', 2048)->change();
        });
    }

    public function down(): void
    {
        // Not narrowed back: URLs longer than 255 characters would be cut off
    }
};
