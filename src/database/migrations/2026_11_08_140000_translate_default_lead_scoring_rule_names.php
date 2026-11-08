<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The default lead scoring rules were stored with raw translation keys as their
 * name and description (crm.scoring.defaults.email_opened.name ...), because the
 * keys were missing from lang/{locale}/crm.php (issue #67). Replace the values
 * that are still exactly such a key with the text in the owner's language
 * (users.locale, falling back to en). Rules a user renamed are left alone.
 */
return new class extends Migration
{
    private const PREFIX = 'crm.scoring.defaults.';

    public function up(): void
    {
        if (!Schema::hasTable('lead_scoring_rules')) {
            return;
        }

        $locales = [];

        DB::table('lead_scoring_rules')
            ->where(fn ($query) => $query
                ->where('name', 'like', self::PREFIX . '%')
                ->orWhere('description', 'like', self::PREFIX . '%'))
            ->select(['id', 'user_id', 'name', 'description'])
            ->chunkById(200, function ($rules) use (&$locales) {
                foreach ($rules as $rule) {
                    $locale = $locales[$rule->user_id] ??= (DB::table('users')->where('id', $rule->user_id)->value('locale') ?: 'en');

                    $changes = [];
                    foreach (['name', 'description'] as $column) {
                        $text = $this->translate($rule->{$column}, $locale);
                        if ($text !== null) {
                            $changes[$column] = $text;
                        }
                    }

                    if ($changes) {
                        DB::table('lead_scoring_rules')->where('id', $rule->id)->update($changes);
                    }
                }
            });
    }

    public function down(): void
    {
        // The raw keys are not worth restoring.
    }

    /** The text for a stored raw key, or null when the value is not one. */
    private function translate(?string $value, string $locale): ?string
    {
        if ($value === null || !preg_match('/^crm\.scoring\.defaults\.[a-z0-9_]+\.(name|description)$/', $value)) {
            return null;
        }

        $text = trans($value, [], $locale);

        return is_string($text) && $text !== $value ? $text : null;
    }
};
