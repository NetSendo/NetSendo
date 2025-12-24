<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ContactListResource;
use App\Http\Resources\Api\V1\SubscriberResource;
use App\Models\ContactList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactListController extends Controller
{
    /**
     * Get all contact lists for the user
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = ContactList::forUser($user->id)
            ->withCount('subscribers')
            ->with('group');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by group
        if ($request->has('group_id')) {
            $query->where('contact_list_group_id', $request->group_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);

        return ContactListResource::collection($query->paginate($perPage));
    }

    /**
     * Get a single contact list
     */
    public function show(Request $request, int $id): ContactListResource|JsonResponse
    {
        $user = $request->user();

        $list = ContactList::forUser($user->id)
            ->withCount('subscribers')
            ->with('group')
            ->find($id);

        if (!$list) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Contact list not found',
            ], 404);
        }

        return new ContactListResource($list);
    }

    /**
     * Get subscribers for a contact list
     */
    public function subscribers(Request $request, int $id): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();

        $list = ContactList::forUser($user->id)->find($id);

        if (!$list) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Contact list not found',
            ], 404);
        }

        $query = $list->subscribers()
            ->with(['tags', 'fieldValues.customField']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 25);

        return SubscriberResource::collection($query->paginate($perPage));
    }
}
