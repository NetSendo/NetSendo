<?php

namespace App\Services\Brain\Knowledge;

/**
 * Splits knowledge entry text into chunks of about TARGET characters for embedding.
 *
 * Paragraphs stay whole where they fit; longer ones are split at sentences, and a sentence
 * that is still too long at words. Each chunk after the first repeats the end of the one
 * before (OVERLAP), so a fact cut at a border is still found.
 */
class KnowledgeChunker
{
    public const TARGET = 1200;

    public const MAX = 1600;

    public const OVERLAP = 150;

    /**
     * @return list<string>
     */
    public function split(string $text): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= self::MAX) {
            return [$text];
        }

        $chunks = [];
        $current = '';
        // The repeated tail does not count against the target, or every chunk after the
        // first would hold one paragraph less
        $allowance = 0;

        foreach ($this->pieces($text) as $piece) {
            $joined = $current === '' ? $piece : $current . "\n\n" . $piece;

            if ($current !== '' && mb_strlen($joined) > self::TARGET + $allowance) {
                $chunks[] = $current;
                $tail = $this->tail($current);
                $current = $tail === '' ? $piece : $tail . ' … ' . $piece;
                $allowance = $tail === '' ? 0 : mb_strlen($tail) + 3;
            } else {
                $current = $joined;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Paragraphs, with the long ones broken into sentences and words.
     *
     * @return list<string>
     */
    private function pieces(string $text): array
    {
        $pieces = [];

        foreach (preg_split('/\n\s*\n/u', $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (mb_strlen($paragraph) <= self::TARGET) {
                $pieces[] = $paragraph;
                continue;
            }

            foreach (preg_split('/(?<=[.!?…])\s+|\n/u', $paragraph) as $sentence) {
                $sentence = trim($sentence);
                if ($sentence === '') {
                    continue;
                }
                array_push($pieces, ...$this->words($sentence));
            }
        }

        return $pieces;
    }

    /**
     * A sentence as it is, or cut at spaces into parts of at most TARGET characters.
     *
     * @return list<string>
     */
    private function words(string $sentence): array
    {
        if (mb_strlen($sentence) <= self::TARGET) {
            return [$sentence];
        }

        $parts = [];
        $current = '';
        foreach (preg_split('/\s+/u', $sentence) as $word) {
            // A single "word" longer than the target (a URL, base64) is cut hard
            while (mb_strlen($word) > self::TARGET) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }
                $parts[] = mb_substr($word, 0, self::TARGET);
                $word = mb_substr($word, self::TARGET);
            }

            $joined = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($joined) > self::TARGET) {
                $parts[] = $current;
                $current = $word;
            } else {
                $current = $joined;
            }
        }
        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /** The last OVERLAP characters of a chunk, starting at a word. */
    private function tail(string $chunk): string
    {
        if (mb_strlen($chunk) <= self::OVERLAP) {
            return '';
        }

        $tail = mb_substr($chunk, -self::OVERLAP);
        $space = mb_strpos($tail, ' ');

        return trim($space === false ? $tail : mb_substr($tail, $space + 1));
    }
}
