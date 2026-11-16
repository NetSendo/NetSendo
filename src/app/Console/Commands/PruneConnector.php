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
 *  - clients without a connection: after a day when they were never connected
 *    (a host that registered and did not finish), after 90 days without use
 *    when they were (a host keeps its client_id and reconnects with it).
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

        // A client that was connected once is kept long after its last connection
        // is gone: the host still has its client_id and uses it when the user
        // connects again. One that never got as far as a connection goes after a day.
        $keepDays = max(1, (int) config('connector.registration.keep_used_clients_days', 90));

        $clients = ConnectorClient::whereDoesntHave('connections')
            ->where('created_at', '<', $dayAgo)
            ->where(fn ($query) => $query
                ->whereNull('last_used_at')
                ->orWhere('last_used_at', '<', now()->subDays($keepDays)))
            // A sign-in that is under way still needs its client.
            ->whereDoesntHave('authCodes', fn ($query) => $query->where('expires_at', '>', now()))
            ->delete();

        $this->info("Connector: removed {$codes} codes, {$tokens} token pairs, {$connections} connections, {$clients} clients.");

        return self::SUCCESS;
    }
}
