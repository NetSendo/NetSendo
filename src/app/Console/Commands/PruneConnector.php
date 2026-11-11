<?php

namespace App\Console\Commands;

use App\Models\ConnectorAuthCode;
use App\Models\ConnectorClient;
use App\Models\ConnectorConnection;
use App\Models\ConnectorToken;
use Illuminate\Console\Command;

/**
 * Housekeeping for the connector: removes what can no longer be used.
 *
 *  - authorization codes older than a day (they live for two minutes);
 *  - token pairs whose refresh token has expired, or that were replaced or
 *    revoked more than a day ago;
 *  - connections with no token left (expired or disconnected apps);
 *  - registered clients that have no connection and are older than a day
 *    (a host that registered and never finished, or whose connections are gone).
 */
class PruneConnector extends Command
{
    protected $signature = 'connector:prune';

    protected $description = 'Remove expired connector codes, tokens, connections and unused clients';

    public function handle(): int
    {
        $dayAgo = now()->subDay();

        $codes = ConnectorAuthCode::where('expires_at', '<', $dayAgo)->delete();

        $tokens = ConnectorToken::where('refresh_expires_at', '<', now())
            ->orWhere('rotated_at', '<', $dayAgo)
            ->orWhere('revoked_at', '<', $dayAgo)
            ->delete();

        $connections = ConnectorConnection::whereDoesntHave('tokens')
            ->where('created_at', '<', $dayAgo)
            ->delete();

        $clients = ConnectorClient::whereDoesntHave('connections')
            ->where('created_at', '<', $dayAgo)
            // A metadata-document client is only a cached download; a pending
            // sign-in with a registered client still needs its row.
            ->whereDoesntHave('authCodes', fn ($query) => $query->where('expires_at', '>', now()))
            ->delete();

        $this->info("Connector: removed {$codes} codes, {$tokens} token pairs, {$connections} connections, {$clients} clients.");

        return self::SUCCESS;
    }
}
