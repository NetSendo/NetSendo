<?php

namespace App\Http\Controllers;

use App\Models\GoogleIntegration;
use App\Services\Mail\GmailOAuthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class IntegrationSettingsController extends Controller
{
    public function __construct(
        private GmailOAuthService $gmailService
    ) {}

    /**
     * Display integration settings.
     */
    public function index()
    {
        return Inertia::render('Settings/Integrations/Index', [
            'integrations' => GoogleIntegration::where('user_id', auth()->id())->get(),
            'google_redirect_uri' => route('settings.mailboxes.gmail.callback'),
        ]);
    }

    /**
     * Store new integration.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|string|max:511',
            'client_secret' => 'required|string|max:511',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['status'] = 'active';

        GoogleIntegration::create($validated);

        return Redirect::route('settings.integrations.index')
            ->with('success', 'Dodano nową integrację Google.');
    }

    /**
     * Verify integration credentials via OAuth.
     */
    public function verify(GoogleIntegration $integration)
    {
        if ($integration->user_id !== auth()->id()) {
            abort(403);
        }

        $state = $this->gmailService->encodeState([
            'integration_id' => $integration->id, 
            'type' => 'verification'
        ]);
        
        $url = $this->gmailService->getAuthorizationUrl($integration, $state);

        return inertia()->location($url);
    }

    /**
     * Update integration (optional, if we want edit).
     */
    public function update(Request $request, GoogleIntegration $integration)
    {
        if ($integration->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|string|max:511',
            'client_secret' => 'required|string|max:511',
        ]);

        $integration->update($validated);

        return Redirect::route('settings.integrations.index')
            ->with('success', 'Zaktualizowano integrację.');
    }

    /**
     * Delete integration.
     */
    public function destroy(GoogleIntegration $integration)
    {
        if ($integration->user_id !== auth()->id()) {
            abort(403);
        }

        $integration->delete();

        return Redirect::route('settings.integrations.index')
            ->with('success', 'Usunięto integrację.');
    }
}
