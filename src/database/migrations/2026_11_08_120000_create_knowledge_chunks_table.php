<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chunks of Brain knowledge entries with their embedding vectors (semantic knowledge base, GOLD).
 *
 * MySQL Community has no vector search, so vectors are stored as packed float32 BLOBs and
 * compared in PHP (App\Services\Brain\Knowledge\SemanticKnowledgeSearch). The chunks are
 * derived from the entries and can always be rebuilt from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_entry_id')->constrained('knowledge_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('chunk_index');
            $table->text('content');
            // Little-endian float32 values of a unit-length vector
            $table->binary('embedding');
            // "<provider>:<model>" — vectors of different models are never compared
            $table->string('embedding_model', 191);
            $table->unsignedSmallInteger('dimensions');
            // sha256 of the entry's title and content the chunk was made from
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->index(['user_id', 'embedding_model']);
            $table->index(['knowledge_entry_id', 'embedding_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
