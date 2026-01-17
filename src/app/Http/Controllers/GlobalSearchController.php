<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use App\Models\CrmContact;
use App\Models\CrmCompany;
use App\Models\CrmTask;
use App\Models\Message;
use App\Models\Media;
use App\Models\Subscriber;
use App\Models\ContactList;
use App\Models\Webinar;

class GlobalSearchController extends Controller
{
    /**
     * Maximum results per category.
     */
    protected const MAX_PER_CATEGORY = 5;

    /**
     * Maximum total results.
     */
    protected const MAX_TOTAL = 25;

    /**
     * Perform global search across all resources.
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'category' => 'nullable|string|in:all,contacts,companies,tasks,messages,media,subscribers,lists,webinars',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->input('query');
        $category = $request->input('category', 'all');
        $limit = $request->input('limit', self::MAX_PER_CATEGORY);
        $userId = auth()->id();

        $results = [];

        // Search in all categories or specific one
        if ($category === 'all' || $category === 'contacts') {
            $results['contacts'] = $this->searchContacts($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'companies') {
            $results['companies'] = $this->searchCompanies($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'tasks') {
            $results['tasks'] = $this->searchTasks($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'messages') {
            $results['messages'] = $this->searchMessages($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'media') {
            $results['media'] = $this->searchMedia($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'subscribers') {
            $results['subscribers'] = $this->searchSubscribers($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'lists') {
            $results['lists'] = $this->searchLists($query, $userId, $limit);
        }

        if ($category === 'all' || $category === 'webinars') {
            $results['webinars'] = $this->searchWebinars($query, $userId, $limit);
        }

        // Calculate total count
        $totalCount = 0;
        foreach ($results as $categoryResults) {
            $totalCount += count($categoryResults);
        }

        return response()->json([
            'query' => $query,
            'category' => $category,
            'results' => $results,
            'total_count' => $totalCount,
        ]);
    }

    /**
     * Search CRM contacts.
     */
    protected function searchContacts(string $query, int $userId, int $limit): array
    {
        return CrmContact::forUser($userId)
            ->with('subscriber')
            ->whereHas('subscriber', function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'type' => 'contact',
                    'title' => $contact->full_name,
                    'subtitle' => $contact->email,
                    'url' => route('crm.contacts.show', $contact->id),
                    'icon' => 'user',
                    'status' => $contact->status,
                ];
            })
            ->toArray();
    }

    /**
     * Search CRM companies.
     */
    protected function searchCompanies(string $query, int $userId, int $limit): array
    {
        return CrmCompany::forUser($userId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('domain', 'like', "%{$query}%")
                    ->orWhere('nip', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'type' => 'company',
                    'title' => $company->name,
                    'subtitle' => $company->domain ?? $company->industry,
                    'url' => route('crm.companies.show', $company->id),
                    'icon' => 'building',
                ];
            })
            ->toArray();
    }

    /**
     * Search CRM tasks.
     */
    protected function searchTasks(string $query, int $userId, int $limit): array
    {
        return CrmTask::forUser($userId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'type' => 'task',
                    'title' => $task->title,
                    'subtitle' => $task->due_date ? $task->due_date->format('Y-m-d H:i') : null,
                    'url' => route('crm.tasks.index') . '?task=' . $task->id,
                    'icon' => $task->type_icon,
                    'status' => $task->status,
                    'priority' => $task->priority,
                ];
            })
            ->toArray();
    }

    /**
     * Search email messages.
     */
    protected function searchMessages(string $query, int $userId, int $limit): array
    {
        return Message::where('user_id', $userId)
            ->email()
            ->where('subject', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'type' => 'message',
                    'title' => $message->subject ?: $message->name,
                    'subtitle' => $message->type,
                    'url' => route('messages.edit', $message->id),
                    'icon' => 'mail',
                    'status' => $message->status,
                ];
            })
            ->toArray();
    }

    /**
     * Search media files.
     */
    protected function searchMedia(string $query, int $userId, int $limit): array
    {
        return Media::where('user_id', $userId)
            ->search($query)
            ->limit($limit)
            ->get()
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'type' => 'media',
                    'title' => $media->original_name,
                    'subtitle' => $media->formatted_size,
                    'url' => route('media.index') . '?media=' . $media->id,
                    'icon' => $media->isImage() ? 'image' : 'file',
                    'thumbnail' => $media->thumbnail_url,
                ];
            })
            ->toArray();
    }

    /**
     * Search subscribers.
     */
    protected function searchSubscribers(string $query, int $userId, int $limit): array
    {
        return Subscriber::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($subscriber) {
                $fullName = trim(($subscriber->first_name ?? '') . ' ' . ($subscriber->last_name ?? ''));
                return [
                    'id' => $subscriber->id,
                    'type' => 'subscriber',
                    'title' => $fullName ?: $subscriber->email,
                    'subtitle' => $fullName ? $subscriber->email : null,
                    'url' => route('subscribers.show', $subscriber->id),
                    'icon' => 'users',
                    'status' => $subscriber->status,
                ];
            })
            ->toArray();
    }

    /**
     * Search contact lists.
     */
    protected function searchLists(string $query, int $userId, int $limit): array
    {
        return ContactList::where('user_id', $userId)
            ->where('name', 'like', "%{$query}%")
            ->limit($limit)
            ->get()
            ->map(function ($list) {
                return [
                    'id' => $list->id,
                    'type' => 'list',
                    'title' => $list->name,
                    'subtitle' => $list->subscribers_count . ' subskrybentów',
                    'url' => route('mailing-lists.show', $list->id),
                    'icon' => 'list',
                ];
            })
            ->toArray();
    }

    /**
     * Search webinars.
     */
    protected function searchWebinars(string $query, int $userId, int $limit): array
    {
        return Webinar::where('user_id', $userId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($webinar) {
                return [
                    'id' => $webinar->id,
                    'type' => 'webinar',
                    'title' => $webinar->name,
                    'subtitle' => $webinar->type === 'live' ? 'Na żywo' : ($webinar->type === 'auto' ? 'Auto' : 'Nagrany'),
                    'url' => route('webinars.show', $webinar->id),
                    'icon' => 'video',
                    'status' => $webinar->status,
                ];
            })
            ->toArray();
    }
}
