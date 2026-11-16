<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Brain API (/api/v1/brain/*) used to accept any valid key whatever its scopes, so a key
 * restricted to, say, subscribers:read could read and change Brain. It now needs the new
 * brain:read / brain:write scopes.
 *
 * Keys are stored with their permission set frozen at creation time. Only a key that holds
 * every scope that existed before this change — a full-access key — keeps Brain access and
 * gets both new scopes. Any narrower key loses Brain access; the owner can add the Brain
 * scopes in Settings → API Keys if the integration really needs them.
 */
return new class extends Migration
{
    private const FULL_ACCESS = [
        'subscribers:read', 'subscribers:write',
        'lists:read', 'lists:write',
        'tags:read',
        'notifications:write',
        'webhooks:read', 'webhooks:write',
        'email:read', 'email:write',
        'sms:read', 'sms:write',
        'messages:read', 'messages:write',
        'funnels:read', 'funnels:write',
    ];

    private const NEW_PERMISSIONS = ['brain:read', 'brain:write'];

    public function up(): void
    {
        DB::table('api_keys')->orderBy('id')->chunkById(200, function ($keys) {
            foreach ($keys as $key) {
                $permissions = json_decode((string) $key->permissions, true);

                if (!is_array($permissions) || array_diff(self::FULL_ACCESS, $permissions) !== []) {
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
