<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An access token and the refresh token issued with it. Only SHA-256 hashes are stored. */
class ConnectorToken extends Model
{
    protected $fillable = [
        'connector_connection_id',
        'access_token_hash',
        'access_expires_at',
        'refresh_token_hash',
        'refresh_expires_at',
        'rotated_at',
        'revoked_at',
    ];

    protected $casts = [
        'access_expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ConnectorConnection::class, 'connector_connection_id');
    }
}
