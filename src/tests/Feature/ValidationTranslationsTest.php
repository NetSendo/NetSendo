<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Validation, login and password reset errors were always in English (issue #74):
 * Laravel ships its texts in English only and lang/{pl,de,es} had no validation.php,
 * passwords.php or pagination.php, and auth.php had no failed/throttle.
 */
class ValidationTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['pl', 'de', 'es'];

    private const FRAMEWORK_FILES = ['validation', 'passwords', 'pagination'];

    public function test_every_framework_key_has_a_text_in_polish_german_and_spanish(): void
    {
        $frameworkLang = base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en');

        foreach (self::FRAMEWORK_FILES as $file) {
            $english = $this->flatten(require "{$frameworkLang}/{$file}.php");
            foreach (self::LOCALES as $locale) {
                $path = lang_path("{$locale}/{$file}.php");
                $this->assertFileExists($path);
                $translated = $this->flatten(require $path);

                foreach ($english as $key => $text) {
                    $this->assertArrayHasKey($key, $translated, "{$locale}/{$file}.php has no {$key}");
                    $this->assertNotSame($text, $translated[$key], "{$locale}/{$file}.php: {$key} is still English");
                    $this->assertSame($this->placeholders($text), $this->placeholders($translated[$key]), "{$locale}/{$file}.php: {$key} placeholders");
                }
            }
        }

        foreach (self::LOCALES as $locale) {
            foreach (['auth.failed', 'auth.throttle'] as $key) {
                $this->assertNotSame(trans($key, [], 'en'), trans($key, [], $locale), "{$locale}: {$key} is still English");
            }
            // auth.password stays the field label of the login form
            $this->assertNotSame(trans('auth.password', [], 'en'), trans('auth.password', [], $locale));
        }
    }

    public function test_a_failing_rule_answers_in_the_current_language(): void
    {
        $expected = [
            'pl' => 'Pole adres email jest wymagane.',
            'de' => 'Das Feld E-Mail-Adresse ist erforderlich.',
            'es' => 'El campo correo electrónico es obligatorio.',
            'en' => 'The email field is required.',
        ];

        foreach ($expected as $locale => $message) {
            app()->setLocale($locale);
            $errors = Validator::make(['email' => ''], ['email' => 'required'])->errors();
            $this->assertSame($message, $errors->first('email'), $locale);
        }

        app()->setLocale('pl');
        $this->assertSame(
            'Liczba znaków w polu nazwa może wynosić najwyżej 3.',
            Validator::make(['name' => 'Lista'], ['name' => 'max:3'])->errors()->first('name')
        );
    }

    public function test_a_polish_user_sees_panel_validation_errors_in_polish(): void
    {
        $user = User::factory()->create(['locale' => 'pl']);

        $this->actingAs($user)
            ->from(route('mailing-lists.create'))
            ->post(route('mailing-lists.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Pole nazwa jest wymagane.']);
    }

    public function test_a_failed_login_is_reported_in_the_browsers_language(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'], ['Accept-Language' => 'pl-PL,pl;q=0.9'])
            ->assertSessionHasErrors(['email' => 'Podane dane logowania są nieprawidłowe.']);
        $this->assertGuest();
    }

    public function test_the_login_throttle_message_keeps_the_seconds(): void
    {
        $this->assertSame('Zbyt wiele prób logowania. Spróbuj ponownie za 42 s.', trans('auth.throttle', ['seconds' => 42], 'pl'));
        $this->assertStringContainsString('42', trans('auth.throttle', ['seconds' => 42], 'de'));
        $this->assertStringContainsString('42', trans('auth.throttle', ['seconds' => 42], 'es'));
    }

    /** @return array<string, string> */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $flat = [];
        foreach ($lines as $key => $value) {
            if (in_array($key, ['custom', 'attributes'], true) && $prefix === '') {
                continue;
            }
            if (is_array($value)) {
                $flat += $this->flatten($value, "{$prefix}{$key}.");
            } else {
                $flat["{$prefix}{$key}"] = $value;
            }
        }

        return $flat;
    }

    /** @return list<string> */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/', $text, $matches);
        $found = array_values(array_unique($matches[0]));
        sort($found);

        return $found;
    }
}
