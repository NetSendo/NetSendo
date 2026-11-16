<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Success and error messages showed raw keys such as polar.settings_saved
 * (issue #72): 15 language files under lang/{locale}/ were missing, and some
 * keys were missing from files that exist. Every literal key used in app/
 * must resolve in every panel language (a German or Spanish text may still
 * fall back to English).
 */
class BackendTranslationKeysTest extends TestCase
{
    private const LOCALES = ['en', 'pl', 'de', 'es'];

    /** __('file.key'), trans('file.key', ...), trans_choice(...), Lang::get(...) with a complete literal key */
    private const KEY_PATTERN = '/(?:\b__|\btrans|\btrans_choice|Lang::get|Lang::choice)\(\s*([\'"])([a-z0-9_\-]+\.[A-Za-z0-9_.\-]+)\1\s*[,)]/';

    public function test_every_literal_key_in_app_resolves_in_all_four_languages(): void
    {
        $keys = $this->literalKeys();
        $this->assertGreaterThan(500, count($keys), 'The scan found too few keys; check KEY_PATTERN');

        $missing = [];
        foreach ($keys as $key => $where) {
            foreach (self::LOCALES as $locale) {
                if (trans($key, [], $locale) === $key) {
                    $missing[] = "{$locale}: {$key} ({$where})";
                }
            }
        }

        $this->assertSame([], $missing, "Keys without a text:\n" . implode("\n", $missing));
    }

    public function test_messages_of_the_restored_files_are_translated(): void
    {
        $this->assertSame('Ustawienia Polar zostały zapisane.', trans('polar.settings_saved', [], 'pl'));
        $this->assertSame('Tpay settings saved.', trans('tpay.settings_saved', [], 'en'));
        $this->assertSame('Deleted 3 old logs', trans('cron.logs_cleared', ['count' => 3], 'en'));
        $this->assertSame(
            'Zsynchronizowano 5 produktów (2 nowych, 3 zaktualizowanych).',
            trans('stripe.sync_success', ['synced' => 5, 'created' => 2, 'updated' => 3], 'pl')
        );
        $this->assertSame('Überfällige Aufgabe: Call back', trans('crm.notifications.task_overdue.subject', ['title' => 'Call back'], 'de'));
    }

    /** @return array<string, string> key => first file:line that uses it */
    private function literalKeys(): array
    {
        $keys = [];
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = $file->getContents();
            preg_match_all(self::KEY_PATTERN, $source, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[2] as [$key, $offset]) {
                $keys[$key] ??= $file->getRelativePathname() . ':' . (substr_count(substr($source, 0, $offset), "\n") + 1);
            }
        }

        return $keys;
    }
}
