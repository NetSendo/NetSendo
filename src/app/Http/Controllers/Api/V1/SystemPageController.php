<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContactList;
use App\Models\SystemPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * System pages (the HTML pages shown after signup, activation, unsubscribe
 * and preference changes) over the API, with the same copy-on-write rules as
 * Settings → System pages. Only list overrides may change their slug.
 */
class SystemPageController extends SystemContentController
{
    protected function model(): string
    {
        return SystemPage::class;
    }

    protected function label(): string
    {
        return 'system page';
    }

    protected function order(): array
    {
        // Web screen order (App\Http\Controllers\SystemPageController::index), then the rest.
        return [
            'signup_success',
            'signup_exists',
            'signup_exists_active',
            'signup_exists_inactive',
            'signup_error',
            'activation_success',
            'activation_error',
            'unsubscribe_confirm',
            'unsubscribe_confirm_sent',
            'unsubscribe_success',
            'unsubscribe_error',
            'preference_confirm_sent',
            'preference_update_success',
        ];
    }

    protected function catalog(): array
    {
        return [
            'signup_success' => [
                'description' => 'Shown after a successful form signup (and after a returning contact re-subscribes).',
                'placeholders' => [],
            ],
            'signup_exists' => [
                'description' => 'Generic "you are already on this list" page.',
                'placeholders' => [],
            ],
            'signup_exists_active' => [
                'description' => 'Shown when an already active subscriber signs up or activates again.',
                'placeholders' => [],
            ],
            'signup_exists_inactive' => [
                'description' => 'Shown when the address is already on the list but still waits for activation (double opt-in).',
                'placeholders' => [],
            ],
            'signup_error' => [
                'description' => 'Shown when a form signup fails.',
                'placeholders' => [],
            ],
            'activation_success' => [
                'description' => 'Shown after the subscriber clicks a valid activation link (double opt-in confirmed).',
                'placeholders' => [],
            ],
            'activation_error' => [
                'description' => 'Shown when an activation link is invalid or expired.',
                'placeholders' => [],
            ],
            'unsubscribe_confirm' => [
                'description' => 'Confirmation step shown before unsubscribing.',
                'placeholders' => ['[[unsubscribe-link]]' => 'Link that completes the unsubscribe'],
            ],
            'unsubscribe_confirm_sent' => [
                'description' => 'Shown after the unsubscribe-confirmation email (system email unsubscribe_request) was sent.',
                'placeholders' => [],
            ],
            'unsubscribe_success' => [
                'description' => 'Shown after a successful unsubscribe.',
                'placeholders' => [],
            ],
            'unsubscribe_error' => [
                'description' => 'Shown when an unsubscribe link is invalid or the unsubscribe fails.',
                'placeholders' => [],
            ],
            'preference_confirm_sent' => [
                'description' => 'Shown after a preference change was submitted and the confirmation email (system email preference_confirm) was sent.',
                'placeholders' => [],
            ],
            'preference_update_success' => [
                'description' => 'Shown after the subscriber confirmed their preference change.',
                'placeholders' => [],
            ],
            'unsubscribe_global_confirm' => [
                'description' => 'Choice page for unsubscribing from all lists at once.',
                'placeholders' => [
                    '[[active_list_count]]' => 'Number of lists the contact is subscribed to',
                    '[[global_unsubscribe_url]]' => 'Link that unsubscribes from every list',
                    '[[manage_url]]' => 'Link to the preferences page',
                ],
            ],
            'unsubscribe_global_success' => [
                'description' => 'Shown after unsubscribing from all lists.',
                'placeholders' => ['[[unsubscribed_count]]' => 'Number of lists the contact was removed from'],
            ],
        ];
    }

    protected function builtInPlaceholders(): array
    {
        return [
            '[[list-name]]' => 'Name of the list (when the page is shown in a list context)',
        ];
    }

    protected function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'access' => 'sometimes|in:public,private',
            'slug' => 'sometimes|required|string|max:100|alpha_dash',
        ];
    }

    protected function presentFields(Model $row): array
    {
        return [
            'title' => $row->title,
            'access' => $row->access ?? 'public',
            'url' => $row->url,
        ];
    }

    protected function attributesFor(array $validated, ?Model $target, ?Model $global, ?ContactList $list): array|JsonResponse
    {
        // Content is stored as-is, like the web form (no sanitising there either).
        $attributes = array_intersect_key($validated, array_flip(['title', 'content', 'access', 'slug']));

        if (isset($attributes['slug'])) {
            $current = $target?->slug ?? $global?->slug;

            if (!$list) {
                if ($attributes['slug'] !== $current) {
                    return $this->badRequest('The slug of a global default page cannot be changed. Only list overrides (pass list_id) may change their slug.');
                }
                unset($attributes['slug']);
            } elseif ($attributes['slug'] === $current) {
                unset($attributes['slug']);
            } else {
                $taken = SystemPage::where('contact_list_id', $list->id)
                    ->where('slug', $attributes['slug'])
                    ->when($target, fn ($query) => $query->whereKeyNot($target->getKey()))
                    ->exists();

                if ($taken) {
                    return $this->badRequest("This list already has a system page with the slug '{$attributes['slug']}'.");
                }
            }
        }

        return $attributes;
    }

    protected function copyOnWrite(Model $global, array $attributes): array
    {
        return [
            'title' => $attributes['title'] ?? $global->title,
            'content' => $attributes['content'] ?? $global->content,
            'access' => $attributes['access'] ?? $global->access ?? 'public',
        ];
    }

    protected function warningsFor(Model $row): array
    {
        if ($row->contact_list_id !== null && !array_key_exists($row->slug, $this->catalog())) {
            return [
                "The slug '{$row->slug}' is not a standard system page slug. NetSendo's signup, activation and unsubscribe flows look pages up by their standard slug, so this list will be shown the global default for those flows instead of this page.",
            ];
        }

        return [];
    }
}
