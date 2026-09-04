<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Blade extracts `@php … @endphp` blocks with the regex
 * `/(?<!@)@php(.*?)@endphp/s` before compiling directives. An inline
 * `@php(expression)` placed anywhere before an `@endphp` in the same file is
 * therefore swallowed into that block: everything between them is emitted as
 * raw text and the page dies with an undefined-variable error at runtime.
 *
 * This bit the public webinar thank-you screen (every registration ended in a
 * 500), so the whole view tree is guarded here.
 */
class BladePhpBlockTest extends TestCase
{
    public function test_no_view_mixes_inline_php_with_php_blocks(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
        $offenders = [];

        /** @var \SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if (!preg_match_all('/(?<!@)@php(.*?)@endphp/s', $contents, $matches)) {
                continue;
            }

            foreach ($matches[1] as $captured) {
                // A capture that starts with "(" means an inline @php(…) was
                // pulled into the block; a nested "@php" means the same.
                if (str_starts_with(ltrim($captured), '(') || str_contains($captured, '@php')) {
                    $offenders[] = str_replace($root . '/', '', $file->getPathname());
                    break;
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'These views mix inline @php(...) with a @php ... @endphp block, which Blade miscompiles:',
            ...$offenders,
            'Fix: use the block form everywhere in the file.',
        ]));
    }
}
