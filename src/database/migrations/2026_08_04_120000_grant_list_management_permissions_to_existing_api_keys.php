<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ApiKey::PERMISSIONS gained 'lists:write' and 'notifications:write' for the
 * list-management API. Keys are stored with their permission set frozen at
 * creation time, so every existing full-access key (including the MCP keys AI
 * assistants already use) would otherwise get a 403 on the new endpoints.
 *
 * Grant the new scopes only to keys that already hold write access to
 * subscribers — a deliberately narrow key stays narrow.
 */
return new class extends Migration
{
    private const NEW_PERMISSIONS = ['lists:write', 'notifications:write'];

    public function up(): void
    {
        DB::table('api_keys')->orderBy('id')->chunkById(200, function ($keys) {
            foreach ($keys as $key) {
                $permissions = json_decode((string) $key->permissions, true);

                if (!is_array($permissions) || !in_array('subscribers:write', $permissions, true)) {
                    continue;
                }

                $updated = array_values(array_unique(array_merge($permissions, self::NEW_PERMISSIONS)));

                if (count($updated) === count($permissions)) {
                    continue;
                }

                DB::table('api_keys')
                    ->where('id', $key->id)
                    ->update(['permissions' => json_encode($updated)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('api_keys')->orderBy('id')->chunkById(200, function ($keys) {
            foreach ($keys as $key) {
                $permissions = json_decode((string) $key->permissions, true);

                if (!is_array($permissions)) {
                    continue;
                }

                $reverted = array_values(array_diff($permissions, self::NEW_PERMISSIONS));

                if (count($reverted) === count($permissions)) {
                    continue;
                }

                DB::table('api_keys')
                    ->where('id', $key->id)
                    ->update(['permissions' => json_encode($reverted)]);
            }
        });
    }
};
