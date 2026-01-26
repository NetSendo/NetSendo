<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\UserZoomConnection;
use App\Services\ZoomOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ZoomController extends Controller
{
    public function __construct(
        private ZoomOAuthService $oauthService
    ) {}

    /**
     * Show Zoom settings page.
     */
    public function index()
    {
        $user = auth()->user();
        $connection = UserZoomConnection::forUser($user->id)->active()->first();

        // Get Zoom settings
        $clientId = Setting::where('key', 'zoom_client_id')->value('value');
        $clientSecret = Setting::where('key', 'zoom_client_secret')->value('value');

        return Inertia::render('Settings/Zoom/Index', [
            'settings' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret ? '••••••••••••' : null,
                'has_client_secret' => !empty($clientSecret),
                'redirect_uri' => route('settings.zoom.callback'),
            ],
            'connection' => $connection ? [
                'id' => $connection->id,
                'zoom_email' => $connection->zoom_email,
                'zoom_user_id' => $connection->zoom_user_id,
                'is_active' => $connection->is_active,
                'connected_at' => $connection->created_at->format('Y-m-d H:i'),
            ] : null,
            'is_configured' => $this->oauthService->isConfigured(),
        ]);
    }

    /**
     * Save Zoom API credentials.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
            'client_secret' => 'nullable|string',
        ]);

        Setting::updateOrCreate(
            ['key' => 'zoom_client_id'],
            ['value' => $validated['client_id']]
        );

        // Only update secret if provided (not masked value)
        if ($validated['client_secret'] && !str_contains($validated['client_secret'], '•')) {
            Setting::updateOrCreate(
                ['key' => 'zoom_client_secret'],
                ['value' => $validated['client_secret']]
            );
        }

        return back()->with('success', 'Zoom settings saved successfully.');
    }

    /**
     * Redirect to Zoom OAuth authorization.
     */
    public function connect()
    {
        if (!$this->oauthService->isConfigured()) {
            return back()->with('error', 'Zoom is not configured. Please add Client ID and Secret first.');
        }

        $state = $this->oauthService->encodeState([
            'user_id' => auth()->id(),
            'return_to' => 'settings.zoom.index',
        ]);

        $authUrl = $this->oauthService->getAuthorizationUrl($state);

        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback from Zoom.
     */
    public function callback(Request $request)
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        if ($error) {
            Log::error('Zoom OAuth error', ['error' => $error]);
            return redirect()->route('settings.zoom.index')
                ->with('error', 'Authorization was denied or failed.');
        }

        if (!$code || !$state) {
            return redirect()->route('settings.zoom.index')
                ->with('error', 'Invalid OAuth response.');
        }

        try {
            $stateData = $this->oauthService->decodeState($state);
            $userId = $stateData['user_id'] ?? auth()->id();

            // Exchange code for tokens
            $tokens = $this->oauthService->exchangeCodeForTokens($code);

            // Get user info from Zoom
            $userInfo = $this->oauthService->getUserInfo($tokens['access_token']);

            // Create or update connection
            UserZoomConnection::updateOrCreate(
                ['user_id' => $userId],
                [
                    'zoom_user_id' => $userInfo['id'] ?? null,
                    'zoom_email' => $userInfo['email'] ?? null,
                    'access_token' => Crypt::encryptString($tokens['access_token']),
                    'refresh_token' => isset($tokens['refresh_token'])
                        ? Crypt::encryptString($tokens['refresh_token'])
                        : null,
                    'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                    'is_active' => true,
                ]
            );

            Log::info('Zoom connection established', [
                'user_id' => $userId,
                'zoom_email' => $userInfo['email'] ?? null,
            ]);

            return redirect()->route('settings.zoom.index')
                ->with('success', 'Zoom account connected successfully!');

        } catch (\Exception $e) {
            Log::error('Zoom OAuth callback failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('settings.zoom.index')
                ->with('error', 'Failed to connect Zoom account: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Zoom account.
     */
    public function disconnect()
    {
        $user = auth()->user();
        $connection = UserZoomConnection::forUser($user->id)->first();

        if ($connection) {
            // Revoke tokens
            $this->oauthService->revokeTokens($connection);

            // Delete connection
            $connection->delete();

            Log::info('Zoom connection disconnected', ['user_id' => $user->id]);
        }

        return back()->with('success', 'Zoom account disconnected.');
    }

    /**
     * Get connection status (API endpoint).
     */
    public function status()
    {
        $user = auth()->user();
        $connection = UserZoomConnection::forUser($user->id)->active()->first();

        return response()->json([
            'connected' => $connection !== null,
            'email' => $connection?->zoom_email,
        ]);
    }
}
