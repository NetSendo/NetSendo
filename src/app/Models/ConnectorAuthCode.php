<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One-time authorization code (hash only), bound to a client, a redirect URI and a PKCE challenge. */
class ConnectorAuthCode extends Model
{
    protected $fillable = [
        'code_hash',
        'user_id',
        'connector_client_id',
        'redirect_uri',
        'scopes',
        'code_challenge',
        'resource',
        'expires_at',
        'consumed_at',
        'connector_connection_id',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ConnectorClient::class, 'connector_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ConnectorConnection::class, 'connector_connection_id');
    }
}
