<?php

namespace App\Http\Controllers;

use App\Models\Mailbox;
use App\Models\GoogleIntegration;
use App\Services\Mail\GmailOAuthService;
use Illuminate\Http\Request;

class GmailOAuthController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

    public function __construct(
        private GmailOAuthService $gmailService
    ) {}

    /**
     * Start OAuth flow - redirect to Google
     */
    public function connect(Mailbox $mailbox)
    {
        $this->authorize('update', $mailbox);
        
        \Log::info('GmailOAuthController: connect started', ['mailbox_id' => $mailbox->id]);

        if ($mailbox->provider !== Mailbox::PROVIDER_GMAIL) {
            \Log::warning('GmailOAuthController: Not a Gmail provider');
            return back()->with('error', 'Mailbox is not Gmail provider');
        }

        if (!$mailbox->googleIntegration) {
            \Log::warning('GmailOAuthController: No integration assigned');
            return back()->with('error', 'Mailbox has no Google Integration assigned. Please edit the mailbox settings.');
        }

        $state = $this->gmailService->encodeState(['mailbox_id' => $mailbox->id, 'type' => 'mailbox']);
        $url = $this->gmailService->getAuthorizationUrl($mailbox->googleIntegration, $state);
        
        \Log::info('GmailOAuthController: Redirecting to Google', ['url' => $url]);

        return inertia()->location($url);
    }

    /**
     * OAuth callback from Google
     */
    public function callback(Request $request)
    {
        \Log::info('GmailOAuthController: Callback received', $request->all());

        if ($request->has('error')) {
            \Log::error('GmailOAuthController: Google returned error', ['error' => $request->error]);
            return $this->redirectError($request, 'Google authentication failed: ' . $request->error);
        }

        if (!$request->has('code') || !$request->has('state')) {
            \Log::error('GmailOAuthController: Missing code or state');
            return $this->redirectError($request, 'Invalid response from Google');
        }

        try {
            // Decode state
            $state = $this->gmailService->decodeState($request->state);
            \Log::info('GmailOAuthController: State decoded', $state);
            
            // Handle Verification (Integration Test)
            if (isset($state['type']) && $state['type'] === 'verification') {
                return $this->handleVerificationCallback($request, $state);
            }

            // Handle Mailbox Connection
             if (isset($state['mailbox_id'])) {
                return $this->handleMailboxCallback($request, $state);
            }
            
            throw new \Exception('Unknown callback type');

        } catch (\Exception $e) {
            \Log::error('GmailOAuthController: Exception in callback', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->redirectError($request, 'Connection failed: ' . $e->getMessage());
        }
    }

    private function handleMailboxCallback(Request $request, array $state)
    {
        $mailbox = Mailbox::findOrFail($state['mailbox_id']);
        $this->authorize('update', $mailbox);

        if (!$mailbox->googleIntegration) {
             throw new \Exception('Mailbox Integration missing.');
        }

        // Exchange code for tokens
        $tokens = $this->gmailService->exchangeCodeForTokens($mailbox->googleIntegration, $request->code);
        $this->gmailService->storeTokens($mailbox, $tokens);

        // Get user info (email)
        $userInfo = $this->gmailService->getUserInfo($tokens['access_token']);
        $this->gmailService->storeGmailEmail($mailbox, $userInfo['email']);
        
        // Update sender email to match the authenticated Google account
        $mailbox->update([
            'from_email' => $userInfo['email']
        ]);

        // Set provider as tested/working
        $mailbox->updateTestResult(true, 'Connected via OAuth');

        return redirect()->route('settings.mailboxes.index')
            ->with('success', 'Successfully connected to Gmail!');
    }

    private function handleVerificationCallback(Request $request, array $state)
    {
        $integration = GoogleIntegration::findOrFail($state['integration_id']);
         if ($integration->user_id !== auth()->id()) {
            abort(403);
        }

        // Exchange code tokens just to verify
        $tokens = $this->gmailService->exchangeCodeForTokens($integration, $request->code);
        $userInfo = $this->gmailService->getUserInfo($tokens['access_token']);

        // Mark as active and verified
        $integration->update([
            'status' => 'active',
            'name' => $userInfo['email'], // Update name to match the authenticated email
        ]);

        return redirect()->route('settings.integrations.index')
            ->with('success', 'Autoryzacja pomyślna! Konto Google połączone: ' . $userInfo['email']);
    }

    private function redirectError(Request $request, string $message) 
    {
        // Try to guess where to redirect based on state or referrer?
        // For now default to mailboxes, but if verification failed, maybe integrations?
        // Let's rely on session or referrer if possible, or just default.
        return redirect()->route('settings.mailboxes.index')
            ->with('error', $message);
    }

    public function disconnect(Mailbox $mailbox)
    {
         $this->authorize('update', $mailbox);
         // ... implementation 
         $this->gmailService->disconnect($mailbox);

        return back()->with('success', 'Disconnected from Gmail');
    }
}
