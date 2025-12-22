<?php

namespace App\Services\Mail\Providers;

use App\Services\Mail\MailProviderInterface;
use App\Services\Mail\GmailOAuthService;
use App\Models\Mailbox;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Http;
use Exception;
use Log;

class GmailProvider implements MailProviderInterface
{
    private const GMAIL_API_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function __construct(
        private GmailOAuthService $oauthService,
        private Mailbox $mailbox,
        private string $fromEmail,
        private string $fromName
    ) {}

    public function send(string $to, string $toName, string $subject, string $htmlContent, array $headers = []): bool
    {
        try {
            // Get valid access token
            $accessToken = $this->oauthService->getValidAccessToken($this->mailbox);

            // Create MIME message using Symfony Mailer component
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($to, $toName))
                ->subject($subject)
                ->html($htmlContent);

            // Add custom headers
            foreach ($headers as $name => $value) {
                $email->getHeaders()->addTextHeader($name, $value);
            }

            // Get raw content headers + body
            $rawMessage = $email->toString();

            // Base64URL encode the message
            $encodedMessage = $this->base64UrlEncode($rawMessage);

            // Send via Gmail API
            $response = Http::withToken($accessToken)
                ->post(self::GMAIL_API_URL, [
                    'raw' => $encodedMessage
                ]);

            if (!$response->successful()) {
                Log::error('Gmail API send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error' => $response->json('error')
                ]);
                throw new Exception('Gmail API Error: ' . $response->json('error.message', 'Unknown error'));
            }

            return true;
        } catch (Exception $e) {
            Log::error("GmailProvider send failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function testConnection(?string $toEmail = null): array
    {
        try {
            // Test if we can get a valid token
            $accessToken = $this->oauthService->getValidAccessToken($this->mailbox);

            // Check profile to verify scope
            // Check profile to verify scope - use UserInfo (OIDC) instead of Gmail API to avoid extra scopes
            $profile = $this->oauthService->getUserInfo($accessToken);
            $emailAddress = $profile['email'];

            // If test recipient is provided, try to send an email
            if ($toEmail) {
                $subject = "Test Connection for {$this->mailbox->name}";
                $body = "<h1>Connection Successful!</h1><p>This email confirms that your Gmail mailbox '{$this->mailbox->name}' is correctly connected and able to send emails.</p>";
                
                $this->send($toEmail, 'Test Recipient', $subject, $body);
                
                return [
                    'success' => true,
                    'message' => "Connected successfully as {$emailAddress} and test email sent to {$toEmail}",
                ];
            }

            return [
                'success' => true,
                'message' => "Connected successfully to Gmail API as {$emailAddress}",
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    public function getProviderName(): string
    {
        return 'Gmail';
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
