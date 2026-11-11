<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AI host that connects to this installation through the connector (/mcp).
 *
 * All clients are public OAuth clients: there is no client secret, the
 * authorization code is protected by PKCE.
 */
class ConnectorClient extends Model
{
    public const TYPE_DYNAMIC = 'dynamic';   // RFC 7591 registration
    public const TYPE_METADATA = 'metadata'; // Client ID Metadata Document

    protected $fillable = [
        'identifier',
        'registration_type',
        'metadata_url',
        'name',
        'redirect_uris',
        'client_uri',
        'logo_uri',
        'metadata_fetched_at',
        'last_used_at',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
        'metadata_fetched_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(ConnectorConnection::class);
    }

    public function authCodes(): HasMany
    {
        return $this->hasMany(ConnectorAuthCode::class);
    }

    /** The client_id as the host sends it: the document URL, or the registered identifier. */
    public function publicId(): string
    {
        return $this->registration_type === self::TYPE_METADATA
            ? (string) $this->metadata_url
            : $this->identifier;
    }

    /**
     * Where the identity of this client comes from: the host of its metadata
     * document (verified by downloading it), or nothing for a self-registered
     * client, whose name is whatever it chose to send.
     */
    public function verifiedHost(): ?string
    {
        if ($this->registration_type !== self::TYPE_METADATA) {
            return null;
        }

        return parse_url((string) $this->metadata_url, PHP_URL_HOST) ?: null;
    }
}
