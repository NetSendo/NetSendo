<?php

namespace App\Services\Nmi\Providers;

use RuntimeException;

/**
 * A provider's API refused a request or could not be reached. The message is written
 * for the person in front of the panel: it names the provider and repeats its reason.
 */
class IpProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    /** The API key is wrong, expired, or lacks a permission */
    public function isAuthorization(): bool
    {
        return in_array($this->status, [401, 403], true);
    }
}
