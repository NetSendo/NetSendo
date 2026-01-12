<?php

namespace App\Http\Controllers;

use App\Models\Name;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class NameDatabaseController extends Controller
{
    /**
     * Display the name database settings page
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Name::availableTo($user->id);

        // Apply search filter
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        // Apply country filter
        if ($country = $request->get('country')) {
            $query->forCountry($country);
        }

        // Apply gender filter
        if ($gender = $request->get('gender')) {
            $query->forGender($gender);
        }

        // Apply source filter
        if ($source = $request->get('source')) {
            if ($source === 'system') {
                $query->system();
            } elseif ($source === 'user') {
                $query->userDefined($user->id);
            }
        }

        $names = $query->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        // Get statistics
        $stats = $this->getStats($user->id);

        return Inertia::render('Settings/NameDatabase/Index', [
            'names' => $names,
            'stats' => $stats,
            'countries' => Name::COUNTRIES,
            'filters' => [
                'search' => $request->get('search', ''),
                'country' => $request->get('country', ''),
                'gender' => $request->get('gender', ''),
                'source' => $request->get('source', ''),
            ],
        ]);
    }

    /**
     * Store a new user-defined name
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'vocative' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female,neutral',
            'country' => 'required|string|size:2',
        ]);

        $user = auth()->user();

        // Check if name already exists for this user
        $exists = Name::where('name', mb_strtolower(trim($validated['name'])))
            ->where('country', $validated['country'])
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => __('names.errors.already_exists')]);
        }

        Name::create([
            'name' => mb_strtolower(trim($validated['name'])),
            'vocative' => $validated['vocative'] ? mb_strtolower(trim($validated['vocative'])) : null,
            'gender' => $validated['gender'],
            'country' => $validated['country'],
            'source' => 'user',
            'user_id' => $user->id,
        ]);

        return back()->with('success', __('names.created'));
    }

    /**
     * Update a user-defined name
     */
    public function update(Request $request, Name $name)
    {
        $user = auth()->user();

        // Only user-defined names can be edited
        if ($name->source !== 'user' || $name->user_id !== $user->id) {
            abort(403, __('names.errors.cannot_edit_system'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'vocative' => 'nullable|string|max:100',
            'gender' => 'required|in:male,female,neutral',
            'country' => 'required|string|size:2',
        ]);

        $name->update([
            'name' => mb_strtolower(trim($validated['name'])),
            'vocative' => $validated['vocative'] ? mb_strtolower(trim($validated['vocative'])) : null,
            'gender' => $validated['gender'],
            'country' => $validated['country'],
        ]);

        return back()->with('success', __('names.updated'));
    }

    /**
     * Delete a user-defined name
     */
    public function destroy(Name $name)
    {
        $user = auth()->user();

        // Only user-defined names can be deleted
        if ($name->source !== 'user' || $name->user_id !== $user->id) {
            abort(403, __('names.errors.cannot_delete_system'));
        }

        $name->delete();

        return back()->with('success', __('names.deleted'));
    }

    /**
     * Import names from CSV
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
            'country' => 'required|string|size:2',
        ]);

        $user = auth()->user();
        $country = $request->get('country');

        $file = $request->file('file');
        $content = file_get_contents($file->getRealPath());
        $lines = array_filter(explode("\n", $content));

        $imported = 0;
        $skipped = 0;

        foreach ($lines as $index => $line) {
            // Skip header row
            if ($index === 0 && (stripos($line, 'name') !== false || stripos($line, 'imię') !== false)) {
                continue;
            }

            $parts = str_getcsv(trim($line));

            if (count($parts) < 2) {
                $skipped++;
                continue;
            }

            $name = mb_strtolower(trim($parts[0]));
            $gender = strtolower(trim($parts[1]));

            // Normalize gender
            if (in_array($gender, ['m', 'male', 'męski', 'mężczyzna'])) {
                $gender = 'male';
            } elseif (in_array($gender, ['f', 'female', 'żeński', 'kobieta'])) {
                $gender = 'female';
            } else {
                $gender = 'neutral';
            }

            // Check if exists
            $exists = Name::where('name', $name)
                ->where('country', $country)
                ->where('user_id', $user->id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Name::create([
                'name' => $name,
                'gender' => $gender,
                'country' => $country,
                'source' => 'user',
                'user_id' => $user->id,
            ]);

            $imported++;
        }

        return back()->with('success', __('names.import_success', [
            'imported' => $imported,
            'skipped' => $skipped,
        ]));
    }

    /**
     * Export names to CSV
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        $source = $request->get('source', 'all');
        $country = $request->get('country');

        $query = Name::query();

        if ($source === 'user') {
            $query->userDefined($user->id);
        } elseif ($source === 'system') {
            $query->system();
        } else {
            $query->availableTo($user->id);
        }

        if ($country) {
            $query->forCountry($country);
        }

        $names = $query->orderBy('name')->get();

        $csv = "name,gender,country,source\n";
        foreach ($names as $name) {
            $csv .= "{$name->name},{$name->gender},{$name->country},{$name->source}\n";
        }

        $filename = 'names_export_' . date('Y-m-d_His') . '.csv';

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Get statistics for the name database
     */
    protected function getStats(int $userId): array
    {
        $systemCount = Name::system()->count();
        $userCount = Name::userDefined($userId)->count();

        // Count by country
        $byCountry = Name::availableTo($userId)
            ->selectRaw('country, COUNT(*) as count')
            ->groupBy('country')
            ->pluck('count', 'country')
            ->toArray();

        return [
            'total' => $systemCount + $userCount,
            'system' => $systemCount,
            'user' => $userCount,
            'by_country' => $byCountry,
        ];
    }

    /**
     * Get gender matching statistics/preview
     */
    public function genderMatchingStats(Request $request)
    {
        $user = auth()->user();
        $country = $request->get('country', 'PL');

        $genderService = app(\App\Services\GenderService::class);
        $preview = $genderService->getMatchingPreview($user->id, $country);
        $subscriberStats = $genderService->getGenderStats($user->id);

        // Check for running job
        $progressKey = \App\Jobs\MatchSubscriberGendersJob::getProgressKey($user->id);
        $progress = \Illuminate\Support\Facades\Cache::get($progressKey);

        return response()->json([
            'preview' => $preview,
            'subscriber_stats' => $subscriberStats,
            'job_progress' => $progress,
        ]);
    }

    /**
     * Run gender matching for all subscribers
     */
    public function matchGenders(Request $request)
    {
        $user = auth()->user();
        $country = $request->get('country', 'PL');
        $async = $request->boolean('async', true);

        $genderService = app(\App\Services\GenderService::class);

        if ($async) {
            // Run as background job
            \App\Jobs\MatchSubscriberGendersJob::dispatch($user->id, $country);

            return response()->json([
                'message' => __('names.gender_matching.job_started'),
                'status' => 'queued',
            ]);
        }

        // Run synchronously (for small datasets)
        $results = $genderService->matchGenderForAllSubscribers($user->id, $country, false);

        return response()->json([
            'message' => __('names.gender_matching.completed'),
            'results' => $results,
        ]);
    }

    /**
     * Get progress of running gender matching job
     */
    public function matchGendersProgress()
    {
        $user = auth()->user();
        $progressKey = \App\Jobs\MatchSubscriberGendersJob::getProgressKey($user->id);
        $progress = \Illuminate\Support\Facades\Cache::get($progressKey);

        if (!$progress) {
            return response()->json([
                'status' => 'no_job',
            ]);
        }

        return response()->json($progress);
    }

    /**
     * Clear gender matching progress cache
     */
    public function clearMatchGendersProgress()
    {
        $user = auth()->user();
        $progressKey = \App\Jobs\MatchSubscriberGendersJob::getProgressKey($user->id);
        \Illuminate\Support\Facades\Cache::forget($progressKey);

        return response()->json(['status' => 'cleared']);
    }
}

