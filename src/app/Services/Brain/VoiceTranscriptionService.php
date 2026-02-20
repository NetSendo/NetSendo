<?php

namespace App\Services\Brain;

use App\Models\AiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VoiceTranscriptionService
{
    /**
     * Transcribe an audio file using OpenAI Whisper API.
     *
     * @param string $audioFilePath Absolute path to the audio file.
     * @param string|null $language Optional ISO-639-1 language hint (e.g. 'pl', 'en').
     * @return string The transcribed text.
     *
     * @throws \Exception If no OpenAI integration is configured or transcription fails.
     */
    public function transcribe(string $audioFilePath, ?string $language = null, ?string $originalFilename = null): string
    {
        $integration = $this->resolveOpenAiIntegration();

        if (!$integration) {
            throw new \Exception('No active OpenAI integration configured. Whisper transcription requires an OpenAI API key.');
        }

        $apiKey = $integration->api_key;
        $baseUrl = rtrim($integration->getEffectiveBaseUrl(), '/');

        // Determine the filename to send to Whisper API.
        // PHP temp uploads (e.g. /tmp/phpXXXXXX) have no extension,
        // so Whisper can't detect the format → 422 "Unrecognized file format".
        $filename = $originalFilename ?: basename($audioFilePath);
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.webm'; // Safe default — Whisper supports webm
        }

        $request = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])
            ->timeout(60)
            ->attach(
                'file',
                file_get_contents($audioFilePath),
                $filename
            )
            ->post("{$baseUrl}/audio/transcriptions", [
                'model' => 'whisper-1',
                'response_format' => 'text',
                'language' => $language,
            ]);

        if (!$request->successful()) {
            $error = $request->json('error.message') ?? $request->body();
            Log::error('Whisper transcription failed', [
                'status' => $request->status(),
                'error' => $error,
            ]);
            throw new \Exception('Transcription failed: ' . $error);
        }

        $text = trim($request->body());

        if (empty($text)) {
            throw new \Exception('Transcription returned empty text.');
        }

        return $text;
    }

    /**
     * Transcribe audio from a URL (downloads to temp file first).
     *
     * @param string $url URL to the audio file.
     * @param string $extension File extension (e.g. 'ogg', 'mp3').
     * @param string|null $language Optional language hint.
     * @return string The transcribed text.
     */
    public function transcribeFromUrl(string $url, string $extension = 'ogg', ?string $language = null): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'voice_') . '.' . $extension;

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                throw new \Exception('Failed to download audio file from URL.');
            }

            file_put_contents($tempFile, $response->body());

            return $this->transcribe($tempFile, $language);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Resolve the first active OpenAI integration.
     */
    protected function resolveOpenAiIntegration(): ?AiIntegration
    {
        return AiIntegration::where('provider', 'openai')
            ->where('is_active', true)
            ->whereNotNull('api_key')
            ->first();
    }
}
