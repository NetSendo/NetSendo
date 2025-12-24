<?php

namespace App\Services\Sms;

/**
 * Data Transfer Object for SMS send results.
 */
class SmsResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly ?float $credits = null,
        public readonly ?int $parts = null, // Number of SMS parts (for long messages)
        public readonly array $metadata = []
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(
        string $messageId,
        ?float $credits = null,
        ?int $parts = null,
        array $metadata = []
    ): self {
        return new self(
            success: true,
            messageId: $messageId,
            credits: $credits,
            parts: $parts,
            metadata: $metadata
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(
        string $errorMessage,
        ?string $errorCode = null,
        array $metadata = []
    ): self {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            metadata: $metadata
        );
    }

    /**
     * Convert to array for logging/storage.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message_id' => $this->messageId,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'credits' => $this->credits,
            'parts' => $this->parts,
            'metadata' => $this->metadata,
        ];
    }
}
