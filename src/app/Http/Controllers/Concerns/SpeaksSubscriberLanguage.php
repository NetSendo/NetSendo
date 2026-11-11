<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\App;

/**
 * Pages a subscriber opens from an email (preferences, unsubscribe) speak the
 * subscriber's language, else the panel language of the account that sent the
 * email, else what SetLocale chose from the browser.
 */
trait SpeaksSubscriberLanguage
{
    protected function useSubscriberLocale(Subscriber $subscriber): void
    {
        $supported = array_keys(config('localization.supported_locales', []));

        $candidates = [
            $subscriber->language,
            $subscriber->user_id ? User::whereKey($subscriber->user_id)->value('locale') : null,
        ];

        foreach ($candidates as $locale) {
            $locale = is_string($locale) ? strtolower(substr(trim($locale), 0, 2)) : '';

            if ($locale !== '' && in_array($locale, $supported, true)) {
                App::setLocale($locale);

                return;
            }
        }
    }
}
