<?php

namespace Tests\Unit;

use App\Services\Brain\Knowledge\KnowledgeChunker;
use PHPUnit\Framework\TestCase;

class KnowledgeChunkerTest extends TestCase
{
    public function test_short_text_is_one_chunk(): void
    {
        $chunks = (new KnowledgeChunker())->split("  Our course costs 297 EUR.\r\n\r\nIt has 12 modules.  ");

        $this->assertSame(["Our course costs 297 EUR.\n\nIt has 12 modules."], $chunks);
    }

    public function test_empty_text_has_no_chunks(): void
    {
        $this->assertSame([], (new KnowledgeChunker())->split(" \n "));
    }

    public function test_long_text_is_split_into_overlapping_chunks_of_bounded_size(): void
    {
        $paragraphs = [];
        for ($i = 1; $i <= 30; $i++) {
            $paragraphs[] = "Paragraph {$i}. " . str_repeat("Sentence number {$i} about the offer. ", 6);
        }
        $text = implode("\n\n", $paragraphs);

        $chunks = (new KnowledgeChunker())->split($text);

        $this->assertGreaterThan(3, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(KnowledgeChunker::MAX, mb_strlen($chunk));
        }
        // Nothing is lost: every paragraph starts in some chunk
        foreach (range(1, 30) as $i) {
            $this->assertNotEmpty(array_filter($chunks, fn ($chunk) => str_contains($chunk, "Paragraph {$i}.")));
        }
        // The second chunk repeats the end of the first
        $tail = mb_substr($chunks[0], -40);
        $this->assertStringContainsString(trim(mb_substr($tail, mb_strpos($tail, ' ') + 1)), $chunks[1]);
    }

    public function test_a_long_paragraph_without_breaks_is_cut_at_sentences_and_words(): void
    {
        $text = str_repeat('Zażółć gęślą jaźń i opowiedz o produkcie. ', 120)
            . str_repeat('x', 3000);

        $chunks = (new KnowledgeChunker())->split($text);

        $this->assertGreaterThan(4, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(KnowledgeChunker::MAX, mb_strlen($chunk));
            $this->assertTrue(mb_check_encoding($chunk, 'UTF-8'));
        }
    }
}
