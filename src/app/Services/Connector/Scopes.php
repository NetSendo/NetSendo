<?php

namespace App\Services\Connector;

use App\Models\ApiKey;

/**
 * OAuth scopes of the connector. They are the permission names of API keys
 * (ApiKey::PERMISSIONS), so a connected app is limited by the same checks as a
 * key with those permissions.
 */
class Scopes
{
    /** @return list<string> */
    public static function all(): array
    {
        return array_values(array_diff(ApiKey::PERMISSIONS, config('connector.excluded_scopes', [])));
    }

    /**
     * Parse a space-separated `scope` parameter. Unknown names are dropped; an
     * empty request means everything the connector offers.
     *
     * @return list<string>
     */
    public static function parse(?string $scope): array
    {
        $requested = preg_split('/\s+/', trim((string) $scope), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($requested === []) {
            return self::all();
        }

        return array_values(array_intersect(self::all(), $requested));
    }

    /**
     * Keep only known scopes, in the canonical order.
     *
     * @param  array<int, mixed>  $scopes
     * @return list<string>
     */
    public static function filter(array $scopes): array
    {
        return array_values(array_intersect(self::all(), array_filter($scopes, 'is_string')));
    }

    /**
     * Scopes grouped by resource for the consent screen and the panel:
     * [['resource' => 'lists', 'read' => 'lists:read', 'write' => 'lists:write'], …]
     *
     * @return list<array{resource: string, read: ?string, write: ?string}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::all() as $scope) {
            [$resource, $access] = explode(':', $scope, 2) + [1 => 'read'];
            $groups[$resource] ??= ['resource' => $resource, 'read' => null, 'write' => null];
            $groups[$resource][$access === 'write' ? 'write' : 'read'] = $scope;
        }

        return array_values($groups);
    }
}
