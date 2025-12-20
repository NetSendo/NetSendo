<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactList;
use App\Jobs\ExportSubscribersCsv;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    /**
     * Trigger export of subscribers for a contact list
     */
    public function export(Request $request, int $listId): JsonResponse
    {
        $user = $request->user();
        
        // Find contact list ensuring it belongs to user
        $contactList = ContactList::where('user_id', $user->id)
            ->where('id', $listId)
            ->first();

        if (!$contactList) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Contact list not found or access denied'
            ], 404);
        }

        // Dispatch export job
        ExportSubscribersCsv::dispatch($contactList, $user);

        return response()->json([
            'message' => 'Export started. You will receive a notification when it is ready.',
            'list_id' => $contactList->id
        ], 202);
    }

    /**
     * Download exported file
     * Protected by signed URL
     */
    public function download(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        $path = $request->query('path');

        if (!$path || !Storage::exists($path)) {
            abort(404, 'File not found.');
        }

        // Ensure user is downloading their own file? 
        // Signed URL is sufficient proof of authorization for this specific file resource normally,
        // but extra check doesn't hurt. However, path doesn't contain user ID explicitly.
        // We trust the signature.

        return Storage::download($path);
    }
}
