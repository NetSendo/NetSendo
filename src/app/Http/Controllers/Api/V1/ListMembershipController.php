<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SubscriberUnsubscribed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ManagesContactLists;
use App\Models\ContactList;
use App\Models\MessageQueueEntry;
use App\Models\Subscriber;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Bulk membership operations on a list.
 *
 * Every endpoint takes the same selection block — explicit subscriber_ids,
 * explicit emails, or a filter over the list's members — so an agent can act
 * on "these five contacts" or on "everyone who never opened anything" with the
 * same call shape.
 */
class ListMembershipController extends Controller
{
    use ManagesContactLists;

    /** Ceiling on how many members one call may touch. */
    private const MAX_TARGETS = 5000;

    /**
     * Attach subscribers to the list (creating nothing — use import for that).
     */
    public function add(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate($this->selectionRules() + [
            'source_list_id' => 'nullable|integer',
            'source' => 'nullable|string|max:255',
            'trigger_automations' => 'nullable|boolean',
        ]);

        $sourceList = null;
        if (!empty($validated['source_list_id'])) {
            $sourceList = $this->findList($request, (int) $validated['source_list_id']);

            if (!$sourceList) {
                return $this->badRequest('source_list_id does not point to one of your lists.');
            }
        }

        // A filter selects among an existing list's members — the target list
        // cannot be that source, since these contacts are not on it yet.
        if (!empty($validated['filter']) && !$sourceList) {
            return $this->badRequest('Using "filter" here requires "source_list_id" — the list whose members to select from.');
        }

        $subscriberIds = $this->resolveTargets($request, $validated, $sourceList);

        if (empty($subscriberIds)) {
            return $this->badRequest('No subscribers matched the selection.');
        }

        $source = $validated['source'] ?? 'api';
        $triggerAutomations = $validated['trigger_automations'] ?? true;

        $added = 0;
        $reactivated = 0;
        $alreadyActive = 0;

        foreach ($this->eachSubscriber($request, $subscriberIds) as $subscriber) {
            $existing = $subscriber->contactLists()->where('contact_list_id', $list->id)->first();

            if ($existing && $existing->pivot->status === 'active') {
                $alreadyActive++;
                continue;
            }

            if ($triggerAutomations) {
                // addToList() honours resubscription_behavior and fires
                // SubscriberSignedUp, which starts sequences and webhooks.
                $subscriber->addToList($list->id, $source);
            } else {
                $this->attachQuietly($subscriber, $list, $source);
            }

            $existing ? $reactivated++ : $added++;
        }

        return response()->json([
            'data' => [
                'list_id' => $list->id,
                'selected' => count($subscriberIds),
                'added' => $added,
                'reactivated' => $reactivated,
                'already_active' => $alreadyActive,
            ],
            'message' => sprintf('%d added, %d reactivated, %d already active.', $added, $reactivated, $alreadyActive),
        ]);
    }

    /**
     * Detach members from the list. The subscriber records themselves are kept.
     */
    public function remove(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate($this->selectionRules() + [
            'confirm' => 'nullable|boolean',
        ]);

        $subscriberIds = $this->resolveTargets($request, $validated, $list);

        if (empty($subscriberIds)) {
            return $this->badRequest('No subscribers matched the selection.');
        }

        // A filter-based removal can be very wide; require an explicit confirm.
        if (!empty($validated['filter']) && !$request->boolean('confirm')) {
            return response()->json([
                'error' => 'Confirmation Required',
                'message' => sprintf(
                    'This filter matches %d member(s). Re-send with confirm=true to detach them.',
                    count($subscriberIds)
                ),
                'matched' => count($subscriberIds),
            ], 409);
        }

        $removed = 0;

        foreach (array_chunk($subscriberIds, 500) as $chunk) {
            $this->cancelPlannedMessages($chunk, $list);
            $removed += DB::table('contact_list_subscriber')
                ->where('contact_list_id', $list->id)
                ->whereIn('subscriber_id', $chunk)
                ->delete();
        }

        Log::info('API list members removed', [
            'list_id' => $list->id,
            'user_id' => $request->user()->id,
            'removed' => $removed,
        ]);

        return response()->json([
            'data' => ['list_id' => $list->id, 'selected' => count($subscriberIds), 'removed' => $removed],
            'message' => sprintf('%d member(s) detached from the list.', $removed),
        ]);
    }

    /**
     * Bulk membership status change (unsubscribe, reactivate, mark bounced).
     */
    public function status(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate($this->selectionRules() + [
            'status' => ['required', Rule::in(['active', 'inactive', 'unsubscribed', 'bounced'])],
            'reason' => 'nullable|string|max:255',
            'trigger_automations' => 'nullable|boolean',
        ]);

        $subscriberIds = $this->resolveTargets($request, $validated, $list);

        if (empty($subscriberIds)) {
            return $this->badRequest('No subscribers matched the selection.');
        }

        $status = $validated['status'];
        $reason = $validated['reason'] ?? 'api';
        $triggerAutomations = $validated['trigger_automations'] ?? true;
        $changed = 0;

        foreach ($this->eachSubscriber($request, $subscriberIds) as $subscriber) {
            $membership = $subscriber->contactLists()->where('contact_list_id', $list->id)->first();

            if (!$membership || $membership->pivot->status === $status) {
                continue;
            }

            if ($status === 'active') {
                $triggerAutomations
                    ? $subscriber->addToList($list->id, $reason)
                    : $this->attachQuietly($subscriber, $list, $reason);
            } else {
                $subscriber->contactLists()->updateExistingPivot($list->id, [
                    'status' => $status,
                    'unsubscribed_at' => $status === 'unsubscribed' ? now() : $membership->pivot->unsubscribed_at,
                ]);

                $this->cancelPlannedMessages([$subscriber->id], $list);

                if ($status === 'unsubscribed' && $triggerAutomations) {
                    event(new SubscriberUnsubscribed($subscriber, $list, $reason));
                }
            }

            $changed++;
        }

        return response()->json([
            'data' => [
                'list_id' => $list->id,
                'status' => $status,
                'selected' => count($subscriberIds),
                'changed' => $changed,
            ],
            'message' => sprintf('%d member(s) set to "%s".', $changed, $status),
        ]);
    }

    /**
     * Copy members to another list (source membership untouched).
     */
    public function copy(Request $request, int $id): JsonResponse
    {
        return $this->transfer($request, $id, keepSource: true);
    }

    /**
     * Move members to another list (detached from the source afterwards).
     */
    public function move(Request $request, int $id): JsonResponse
    {
        return $this->transfer($request, $id, keepSource: false);
    }

    private function transfer(Request $request, int $id, bool $keepSource): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate($this->selectionRules() + [
            'target_list_id' => 'required|integer',
            'source' => 'nullable|string|max:255',
            'trigger_automations' => 'nullable|boolean',
        ]);

        $target = $this->findList($request, (int) $validated['target_list_id']);

        if (!$target) {
            return $this->badRequest('target_list_id does not point to one of your lists.');
        }

        if ($target->id === $list->id) {
            return $this->badRequest('Source and target list are the same.');
        }

        if ($target->type !== $list->type) {
            return $this->badRequest(
                "Channel mismatch: source list is '{$list->type}', target list is '{$target->type}'."
            );
        }

        $subscriberIds = $this->resolveTargets($request, $validated, $list);

        if (empty($subscriberIds)) {
            return $this->badRequest('No subscribers matched the selection.');
        }

        $source = $validated['source'] ?? ($keepSource ? 'api_copy' : 'api_move');
        $triggerAutomations = $validated['trigger_automations'] ?? true;

        $transferred = 0;
        $alreadyOnTarget = 0;

        foreach ($this->eachSubscriber($request, $subscriberIds) as $subscriber) {
            $existing = $subscriber->contactLists()->where('contact_list_id', $target->id)->first();

            if ($existing && $existing->pivot->status === 'active') {
                $alreadyOnTarget++;
            } else {
                $triggerAutomations
                    ? $subscriber->addToList($target->id, $source)
                    : $this->attachQuietly($subscriber, $target, $source);

                $transferred++;
            }

            if (!$keepSource) {
                $this->cancelPlannedMessages([$subscriber->id], $list);
                $subscriber->contactLists()->detach($list->id);
            }
        }

        Log::info('API list members transferred', [
            'from_list_id' => $list->id,
            'to_list_id' => $target->id,
            'keep_source' => $keepSource,
            'transferred' => $transferred,
        ]);

        return response()->json([
            'data' => [
                'from_list_id' => $list->id,
                'to_list_id' => $target->id,
                'mode' => $keepSource ? 'copy' : 'move',
                'selected' => count($subscriberIds),
                'transferred' => $transferred,
                'already_on_target' => $alreadyOnTarget,
            ],
            'message' => sprintf(
                '%d member(s) %s to list "%s".',
                $transferred,
                $keepSource ? 'copied' : 'moved',
                $target->name
            ),
        ]);
    }

    /**
     * Add or remove tags across a selection of the list's members.
     */
    public function tags(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->requirePermission($request, 'lists:write')) {
            return $denied;
        }

        $list = $this->findList($request, $id);

        if (!$list) {
            return $this->listNotFound();
        }

        $validated = $request->validate($this->selectionRules() + [
            'add' => 'nullable|array',
            'add.*' => 'string|max:255',
            'remove' => 'nullable|array',
            'remove.*' => 'string|max:255',
        ]);

        if (empty($validated['add']) && empty($validated['remove'])) {
            return $this->badRequest('Provide at least one tag name in "add" or "remove".');
        }

        $subscriberIds = $this->resolveTargets($request, $validated, $list);

        if (empty($subscriberIds)) {
            return $this->badRequest('No subscribers matched the selection.');
        }

        $userId = $request->user()->id;

        $toAdd = collect($validated['add'] ?? [])
            ->map(fn ($name) => Tag::firstOrCreate(
                ['user_id' => $userId, 'name' => trim($name)],
                ['color' => '#6366f1']
            ));

        $toRemove = collect($validated['remove'] ?? [])
            ->map(fn ($name) => Tag::where('user_id', $userId)->where('name', trim($name))->first())
            ->filter();

        $tagged = 0;
        $untagged = 0;

        foreach ($this->eachSubscriber($request, $subscriberIds) as $subscriber) {
            foreach ($toAdd as $tag) {
                $subscriber->addTag($tag);
                $tagged++;
            }

            foreach ($toRemove as $tag) {
                $subscriber->removeTag($tag);
                $untagged++;
            }
        }

        return response()->json([
            'data' => [
                'list_id' => $list->id,
                'selected' => count($subscriberIds),
                'tags_added' => $toAdd->pluck('name')->all(),
                'tags_removed' => $toRemove->pluck('name')->all(),
                'tag_operations' => $tagged + $untagged,
            ],
            'message' => sprintf('Tags updated on %d member(s).', count($subscriberIds)),
        ]);
    }

    // ========================================================================
    // Selection
    // ========================================================================

    private function selectionRules(): array
    {
        return [
            'subscriber_ids' => 'nullable|array|max:' . self::MAX_TARGETS,
            'subscriber_ids.*' => 'integer',
            'emails' => 'nullable|array|max:' . self::MAX_TARGETS,
            'emails.*' => 'string|email',
            'filter' => 'nullable|array',
            'filter.status' => 'nullable|in:active,inactive,unsubscribed,bounced,all',
            'filter.tag_ids' => 'nullable|array',
            'filter.tag_ids.*' => 'integer',
            'filter.engaged' => 'nullable|boolean',
            'filter.subscribed_before' => 'nullable|date',
            'filter.subscribed_after' => 'nullable|date',
            'filter.never_opened' => 'nullable|boolean',
            'filter.limit' => 'nullable|integer|min:1|max:' . self::MAX_TARGETS,
        ];
    }

    /**
     * Resolve the selection into subscriber ids the caller actually owns.
     *
     * @param ContactList|null $scopeList list the filter applies to
     * @return int[]
     */
    private function resolveTargets(Request $request, array $validated, ?ContactList $scopeList): array
    {
        $userId = $request->user()->id;
        $ids = [];

        if (!empty($validated['subscriber_ids'])) {
            $ids = Subscriber::where('user_id', $userId)
                ->whereIn('id', $validated['subscriber_ids'])
                ->pluck('id')
                ->all();
        }

        if (!empty($validated['emails'])) {
            $emails = array_map(fn ($e) => mb_strtolower(trim($e)), $validated['emails']);

            $ids = array_merge($ids, Subscriber::where('user_id', $userId)
                ->whereIn('email', $emails)
                ->pluck('id')
                ->all());
        }

        if (!empty($validated['filter']) && $scopeList) {
            $ids = array_merge($ids, $this->filterMembers($scopeList, $validated['filter']));
        }

        return array_slice(array_values(array_unique($ids)), 0, self::MAX_TARGETS);
    }

    /**
     * @return int[]
     */
    private function filterMembers(ContactList $list, array $filter): array
    {
        $query = DB::table('contact_list_subscriber as pivot')
            ->join('subscribers', 'subscribers.id', '=', 'pivot.subscriber_id')
            ->where('pivot.contact_list_id', $list->id)
            ->whereNull('subscribers.deleted_at')
            ->select('subscribers.id')
            ->orderBy('subscribers.id')
            ->limit(min((int) ($filter['limit'] ?? self::MAX_TARGETS), self::MAX_TARGETS));

        $status = $filter['status'] ?? 'active';
        if ($status !== 'all') {
            $query->where('pivot.status', $status);
        }

        if (!empty($filter['tag_ids'])) {
            $query->whereIn('subscribers.id', function ($q) use ($filter) {
                $q->select('subscriber_id')->from('subscriber_tag')->whereIn('tag_id', $filter['tag_ids']);
            });
        }

        if (!empty($filter['subscribed_before'])) {
            $query->where('pivot.subscribed_at', '<=', $filter['subscribed_before']);
        }

        if (!empty($filter['subscribed_after'])) {
            $query->where('pivot.subscribed_at', '>=', $filter['subscribed_after']);
        }

        if (array_key_exists('engaged', $filter) && $filter['engaged'] !== null) {
            if ($filter['engaged']) {
                $query->where(function ($q) {
                    $q->where('subscribers.opens_count', '>', 0)
                        ->orWhere('subscribers.clicks_count', '>', 0);
                });
            } else {
                $query->where('subscribers.opens_count', 0)->where('subscribers.clicks_count', 0);
            }
        }

        if (!empty($filter['never_opened'])) {
            $query->where('subscribers.opens_count', 0);
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Iterate the selection in chunks so a 5k-member operation never loads
     * every model at once.
     *
     * @param int[] $ids
     * @return \Generator<Subscriber>
     */
    private function eachSubscriber(Request $request, array $ids): \Generator
    {
        $userId = $request->user()->id;

        foreach (array_chunk($ids, 250) as $chunk) {
            foreach (Subscriber::where('user_id', $userId)->whereIn('id', $chunk)->get() as $subscriber) {
                yield $subscriber;
            }
        }
    }

    /**
     * Attach/reactivate without firing SubscriberSignedUp — used when the
     * caller explicitly asked not to trigger automations (e.g. migrating an
     * audience between lists without re-running welcome sequences).
     */
    private function attachQuietly(Subscriber $subscriber, ContactList $list, string $source): void
    {
        $existing = $subscriber->contactLists()->where('contact_list_id', $list->id)->first();

        if (!$existing) {
            $subscriber->contactLists()->attach($list->id, [
                'status' => 'active',
                'subscribed_at' => now(),
                'source' => $source,
            ]);

            return;
        }

        $wasActive = $existing->pivot->status === 'active';
        $pivot = ['status' => 'active', 'unsubscribed_at' => null, 'source' => $source];

        if (!$wasActive || ($list->resubscription_behavior ?? 'reset_date') === 'reset_date') {
            $pivot['subscribed_at'] = now();
        }

        if (!$wasActive) {
            $pivot['resubscribed_at'] = now();
        }

        $subscriber->contactLists()->updateExistingPivot($list->id, $pivot);
    }

    /**
     * Drop not-yet-sent queue entries for messages targeting this list.
     *
     * @param int[] $subscriberIds
     */
    private function cancelPlannedMessages(array $subscriberIds, ContactList $list): void
    {
        MessageQueueEntry::whereIn('subscriber_id', $subscriberIds)
            ->whereIn('status', [MessageQueueEntry::STATUS_PLANNED, MessageQueueEntry::STATUS_QUEUED])
            ->whereIn('message_id', function ($query) use ($list) {
                $query->select('message_id')
                    ->from('contact_list_message')
                    ->where('contact_list_id', $list->id);
            })
            ->delete();
    }
}
