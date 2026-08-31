<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Narrow a message audience down to subscribers whose custom fields hold
     * given values ("send only to people whose 'city' is Oświęcim") and narrow
     * the exclusions the same way ("drop from that list only the people whose
     * 'city' is Kraków").
     */
    public function up(): void
    {
        if (!Schema::hasTable('message_field_filters')) {
            Schema::create('message_field_filters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')->constrained()->cascadeOnDelete();
                $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
                // include = only subscribers matching this, exclude = drop them
                $table->string('mode', 16)->default('include');
                $table->string('operator', 32)->default('any_of');
                $table->json('values')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['message_id', 'mode']);
            });
        }

        Schema::table('messages', function (Blueprint $table) {
            // How several filters on the same side combine: all (AND) or any (OR)
            if (!Schema::hasColumn('messages', 'include_field_filter_match')) {
                $table->string('include_field_filter_match', 8)->default('all')->after('trigger_config');
            }
            if (!Schema::hasColumn('messages', 'exclude_field_filter_match')) {
                $table->string('exclude_field_filter_match', 8)->default('all')->after('include_field_filter_match');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_field_filters');

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'include_field_filter_match')) {
                $table->dropColumn('include_field_filter_match');
            }
            if (Schema::hasColumn('messages', 'exclude_field_filter_match')) {
                $table->dropColumn('exclude_field_filter_match');
            }
        });
    }
};
