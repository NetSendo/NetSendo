<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ContactList;
use App\Models\SystemEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System emails (double opt-in, welcome, unsubscribe confirmations, the
 * owner's new-subscriber notification...) over the API, with the same
 * copy-on-write rules as Settings → System emails. Globals must stay active;
 * an email is switched off per list through its list override.
 */
class SystemEmailController extends SystemContentController
{
    protected function model(): string
    {
        return SystemEmail::class;
    }

    protected function label(): string
    {
        return 'system email';
    }

    protected function order(): array
    {
        // Same order as the web screen (App\Http\Controllers\SystemEmailController::index).
        return [
            'signup_confirmation',
            'activation_email',
            'activation_confirmation',
            'subscription_welcome',
            'welcome_email',
            'already_active_resubscribe',
            'inactive_resubscribe',
            'preference_confirm',
            'data_edit_access',
            'unsubscribe_request',
            'unsubscribed_confirmation',
            'new_subscriber_notification',
        ];
    }

    protected function catalog(): array
    {
        return [
            'signup_confirmation' => [
                'description' => 'Double opt-in: sent right after a form signup on a list that requires confirmation. Must contain the activation link.',
                'placeholders' => ['[[activation-link]]' => 'Signed link that confirms the subscription (required)'],
            ],
            'activation_email' => [
                'description' => 'Legacy activation email. Not sent by the current flows (double opt-in uses signup_confirmation).',
                'placeholders' => [],
            ],
            'activation_confirmation' => [
                'description' => 'Sent after the subscriber clicks the activation link and the subscription is confirmed.',
                'placeholders' => [],
            ],
            'subscription_welcome' => [
                'description' => 'Welcome email sent after a signup that needs no confirmation (single opt-in).',
                'placeholders' => [],
            ],
            'welcome_email' => [
                'description' => 'Legacy welcome email. Not sent by the current flows (single opt-in uses subscription_welcome).',
                'placeholders' => [],
            ],
            'already_active_resubscribe' => [
                'description' => 'Sent when an already active subscriber signs up to the same list again.',
                'placeholders' => [],
            ],
            'inactive_resubscribe' => [
                'description' => 'Sent when a previously inactive or unsubscribed contact signs up to the list again.',
                'placeholders' => [],
            ],
            'preference_confirm' => [
                'description' => 'Sent when a subscriber changes their preferences on the preferences page; the change applies after they click the link. If inactive, a built-in fallback text is sent instead (the confirmation itself cannot be switched off).',
                'placeholders' => ['[[confirm-link]]' => 'Signed link that applies the preference change (required)'],
            ],
            'data_edit_access' => [
                'description' => 'Sent when a subscriber asks for access to edit their subscription data.',
                'placeholders' => ['[[edit-link]]' => 'Signed link to the data edit page (required)'],
            ],
            'unsubscribe_request' => [
                'description' => 'Sent when a subscriber asks to unsubscribe and the list requires confirmation. If inactive, a built-in fallback text is sent instead.',
                'placeholders' => ['[[unsubscribe-link]]' => 'Signed link that confirms the unsubscribe (required)'],
            ],
            'unsubscribed_confirmation' => [
                'description' => 'Sent after a subscriber has been unsubscribed. Switch it off per list to send nothing.',
                'placeholders' => ['[[resubscribe-link]]' => 'Signed link that subscribes them again'],
            ],
            'new_subscriber_notification' => [
                'description' => 'Sent to the list owner, not the subscriber, on every new signup. Recipient: the list\'s notification email, else the account default notification email, else the account email.',
                'placeholders' => [],
            ],
        ];
    }

    protected function builtInPlaceholders(): array
    {
        // Replaced by App\Mail\SystemEmailMailable in subject and content.
        return [
            '[[list-name]]' => 'Name of the list',
            '[[email]]' => 'Subscriber email address',
            '[[first-name]]' => 'Subscriber first name (alias [[fname]])',
            '[[!fname]]' => 'First name in the vocative case (Polish)',
            '[[last-name]]' => 'Subscriber last name (alias [[lname]])',
            '[[phone]]' => 'Subscriber phone',
            '[[date]]' => 'Current date and time (Y-m-d H:i)',
        ];
    }

    protected function rules(): array
    {
        return [
            'subject' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'is_active' => 'sometimes|boolean',
        ];
    }

    protected function presentFields(Model $row): array
    {
        return [
            'subject' => $row->subject,
            'is_active' => (bool) $row->is_active,
        ];
    }

    protected function attributesFor(array $validated, ?Model $target, ?Model $global, ?ContactList $list): array|JsonResponse
    {
        if (!$list && array_key_exists('is_active', $validated) && !$validated['is_active']) {
            return $this->globalMustStayActive();
        }

        // Content is stored as-is, like the web form (no sanitising there either).
        return array_intersect_key($validated, array_flip(['subject', 'content', 'is_active']));
    }

    protected function copyOnWrite(Model $global, array $attributes): array
    {
        return [
            'subject' => $attributes['subject'] ?? $global->subject,
            'content' => $attributes['content'] ?? $global->content,
            'is_active' => $attributes['is_active'] ?? true,
        ];
    }

    /**
     * Switch a system email on or off for one list (creates the override
     * from the global default when the list has none yet).
     */
    public function setActive(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        [$list, $error] = $this->listFromRequest($request);
        if ($error) {
            return $error;
        }

        if (!$list) {
            return $this->globalMustStayActive();
        }

        [$global, $override] = $this->rowsFor($slug, $list);
        if (!$global && !$override) {
            return $this->unknownSlug($slug);
        }

        $validated = $request->validate(['is_active' => 'required|boolean']);

        $created = false;
        if ($override) {
            $override->update(['is_active' => $validated['is_active']]);
        } else {
            $override = SystemEmail::create([
                'slug' => $global->slug,
                'name' => $global->name,
                'subject' => $global->subject,
                'content' => $global->content,
                'is_active' => $validated['is_active'],
                'contact_list_id' => $list->id,
            ]);
            $created = true;
        }

        return response()->json([
            'data' => $this->present($override->refresh(), true, $global, false),
            'meta' => ['created_override' => $created],
        ], $created ? 201 : 200);
    }

    private function globalMustStayActive(): JsonResponse
    {
        return $this->badRequest('Global system emails must stay active. Pass list_id to switch this email off for one list.');
    }
}
