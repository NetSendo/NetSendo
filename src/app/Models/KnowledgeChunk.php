<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of a knowledge entry with its embedding vector (semantic knowledge base, GOLD).
 * Derived from the entry: deleted when the entry changes or goes, rebuilt by the indexer.
 */
class KnowledgeChunk extends Model
{
    protected $fillable = [
        'knowledge_entry_id',
        'user_id',
        'chunk_index',
        'content',
        'embedding',
        'embedding_model',
        'dimensions',
        'content_hash',
    ];

    protected $hidden = [
        'embedding',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'dimensions' => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class, 'knowledge_entry_id');
    }
}
