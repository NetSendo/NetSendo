<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Entries may be longer with the semantic knowledge base (GOLD), more than TEXT holds in MySQL.
 * 2. Web research saved entries under categories the AI made up ("marketing",
 *    "marketing_strategy"). Brain reads the knowledge base by category, so it never used them,
 *    and the edit form refused them. They move to "insights", the research default, and keep
 *    their old category as a tag.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'company', 'products', 'brand_voice', 'audience', 'best_practices', 'insights',
        'templates', 'competitors', 'goals', 'style_preference', 'performance_pattern',
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('knowledge_entries', function (Blueprint $table) {
                $table->mediumText('content')->change();
            });
        }

        DB::table('knowledge_entries')
            ->whereNotIn('category', self::CATEGORIES)
            ->orderBy('id')
            ->chunkById(200, function ($entries) {
                foreach ($entries as $entry) {
                    $tags = json_decode($entry->tags ?? '[]', true);
                    $tags = is_array($tags) ? $tags : [];
                    if ($entry->category !== '' && !in_array($entry->category, $tags, true)) {
                        $tags[] = $entry->category;
                    }

                    DB::table('knowledge_entries')->where('id', $entry->id)->update([
                        'category' => 'insights',
                        'tags' => json_encode(array_values($tags)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Moved categories stay in the tags; the column keeps its larger size, because longer
        // entries would not fit back into TEXT
    }
};
