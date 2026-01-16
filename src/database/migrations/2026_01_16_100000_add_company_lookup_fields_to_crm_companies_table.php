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
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('name');
            $table->string('nip', 10)->nullable()->after('country');
            $table->string('regon', 14)->nullable()->after('nip');

            $table->index('nip');
            $table->index('regon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_companies', function (Blueprint $table) {
            $table->dropIndex(['nip']);
            $table->dropIndex(['regon']);
            $table->dropColumn(['country', 'nip', 'regon']);
        });
    }
};
