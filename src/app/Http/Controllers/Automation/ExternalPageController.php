<?php

namespace App\Http\Controllers\Automation;

use App\Http\Controllers\Controller;
use App\Models\ExternalPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;

class ExternalPageController extends Controller
{
    public function index()
    {
        return Inertia::render('Automation/ExternalPage/Index', [
            'externalPages' => ExternalPage::where('user_id', auth()->id())
                ->latest()
                ->paginate(10),
        ]);
    }

    public function create()
    {
        return Inertia::render('Automation/ExternalPage/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'is_redirect' => 'boolean',
            'shared_fields' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        $request->user()->externalPages()->create($validated);

        return redirect()->route('external-pages.index')
            ->with('success', __('External page created successfully.'));
    }

    public function edit(ExternalPage $externalPage)
    {
        $this->authorize('view', $externalPage);

        return Inertia::render('Automation/ExternalPage/Edit', [
            'externalPage' => $externalPage,
        ]);
    }

    public function update(Request $request, ExternalPage $externalPage)
    {
        $this->authorize('update', $externalPage);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'is_redirect' => 'boolean',
            'shared_fields' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        $externalPage->update($validated);

        return redirect()->route('external-pages.index')
            ->with('success', __('External page updated successfully.'));
    }

    public function destroy(ExternalPage $externalPage)
    {
        $this->authorize('delete', $externalPage);

        $externalPage->delete();

        return redirect()->route('external-pages.index')
            ->with('success', __('External page deleted successfully.'));
    }
}
