<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "connected app": one approval on the consent screen. It carries the
 * permissions the user granted; its tokens act as that user.
 */
class ConnectorConnection extends Model
{
    protected $fillable = [
        'user_id',
        'connector_client_id',
        'client_name',
        'scopes',
        'resource',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ConnectorClient::class, 'connector_client_id');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(ConnectorToken::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** End the connection: every token stops working at once. */
    public function revoke(): void
    {
        if (! $this->isRevoked()) {
            $this->forceFill(['revoked_at' => now()])->save();
        }

        $this->tokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * The connection as the API layer understands it: an API key that is not
     * stored anywhere, owned by the connected user and limited to the granted
     * permissions. The API v1 controllers check permissions on it exactly as
     * they do for a real key.
     */
    public function asApiKey(): ApiKey
    {
        $key = new ApiKey([
            'user_id' => $this->user_id,
            'name' => 'Connector: '.$this->client_name,
            'permissions' => array_values($this->scopes ?? []),
        ]);
        $key->setRelation('user', $this->user);

        return $key;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }
}
