<?php

namespace Tests\Feature;

use App\Models\LeadScoringRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The default lead scoring rules were created with raw translation keys as their
 * names (issue #67): crm.scoring.defaults.* was missing from lang/{locale}/crm.php.
 */
class LeadScoringDefaultRulesTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'pl', 'de', 'es'];

    public function test_the_default_rules_are_translated_in_all_four_languages(): void
    {
        foreach (self::LOCALES as $locale) {
            $rules = LeadScoringRule::getDefaultRules($locale);

            $this->assertCount(14, $rules);
            foreach ($rules as $rule) {
                $this->assertStringStartsNotWith('crm.', $rule['name'], "{$locale}: {$rule['event_type']} name");
                $this->assertStringStartsNotWith('crm.', $rule['description'], "{$locale}: {$rule['event_type']} description");
            }
        }

        $this->assertSame('Otwarcie emaila', LeadScoringRule::getDefaultRules('pl')[0]['name']);
        $this->assertSame('Email Opened', LeadScoringRule::getDefaultRules('en')[0]['name']);
    }

    public function test_a_new_account_gets_the_rules_in_its_owners_language(): void
    {
        app()->setLocale('en');
        $user = User::factory()->create(['locale' => 'pl']);

        $rule = LeadScoringRule::where('user_id', $user->id)->where('event_type', 'email_opened')->firstOrFail();
        $this->assertSame('Otwarcie emaila', $rule->name);
        $this->assertSame(14, LeadScoringRule::where('user_id', $user->id)->count());
        $this->assertSame(0, LeadScoringRule::where('user_id', $user->id)->where('name', 'like', 'crm.%')->count());
    }

    public function test_the_migration_renames_stored_raw_keys_in_the_owners_language(): void
    {
        $polish = User::factory()->create(['locale' => 'pl']);
        $german = User::factory()->create(['locale' => 'de']);
        $noLocale = User::factory()->create();
        DB::table('users')->where('id', $noLocale->id)->update(['locale' => null]);
        LeadScoringRule::query()->delete();

        $raw = fn (User $user, string $rule, ?string $name = null) => DB::table('lead_scoring_rules')->insertGetId([
            'user_id' => $user->id,
            'event_type' => $rule,
            'name' => $name ?? "crm.scoring.defaults.{$rule}.name",
            'description' => "crm.scoring.defaults.{$rule}.description",
            'points' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $polishRule = $raw($polish, 'email_opened');
        $germanRule = $raw($german, 'email_clicked');
        $fallbackRule = $raw($noLocale, 'add_to_cart');
        $renamedRule = $raw($polish, 'email_replied', 'My reply rule');

        $migration = require database_path('migrations/2026_11_08_140000_translate_default_lead_scoring_rule_names.php');
        $migration->up();

        $this->assertSame('Otwarcie emaila', LeadScoringRule::find($polishRule)->name);
        $this->assertSame('Punkty za otwarcie wiadomości email', LeadScoringRule::find($polishRule)->description);
        $this->assertSame(trans('crm.scoring.defaults.email_clicked.name', [], 'de'), LeadScoringRule::find($germanRule)->name);
        $this->assertSame('Added to Cart', LeadScoringRule::find($fallbackRule)->name);

        // A name the user chose stays; the description that is still a raw key is translated
        $renamed = LeadScoringRule::find($renamedRule);
        $this->assertSame('My reply rule', $renamed->name);
        $this->assertSame(trans('crm.scoring.defaults.email_replied.description', [], 'pl'), $renamed->description);

        $this->assertSame(0, LeadScoringRule::where('name', 'like', 'crm.%')->orWhere('description', 'like', 'crm.%')->count());
    }
}
