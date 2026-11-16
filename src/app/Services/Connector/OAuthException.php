<?php

namespace App\Services\Connector;

use RuntimeException;

/** An OAuth protocol error (RFC 6749 §5.2): `error`, a description and the HTTP status to answer with. */
class OAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        string $description = '',
        public readonly int $status = 400,
    ) {
        parent::__construct($description);
    }

    /** @return array{error: string, error_description?: string} */
    public function toArray(): array
    {
        return array_filter([
            'error' => $this->error,
            'error_description' => $this->getMessage(),
        ]);
    }
}
