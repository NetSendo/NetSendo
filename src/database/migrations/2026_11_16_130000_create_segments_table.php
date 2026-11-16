<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved dynamic segments: a named set of conditions (lists, tags, fields,
 * opens and clicks, joining date) evaluated each time it is used, so the
 * people in it change by themselves. A message may narrow its list audience
 * to one segment (messages.segment_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('segments')) {
            Schema::create('segments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('match', 8)->default('all');
                $table->json('conditions')->nullable();
                $table->unsignedInteger('subscribers_count')->nullable();
                $table->timestamp('counted_at')->nullable();
                $table->timestamps();

                $table->index('user_id');
            });
        }

        if (!Schema::hasColumn('messages', 'segment_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('messages', 'segment_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('segment_id');
            });
        }

        Schema::dropIfExists('segments');
    }
};
