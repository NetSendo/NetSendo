<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\ConnectorConnection;
use App\Models\User;
use App\Services\Connector\ConnectorUrls;
use App\Services\Connector\Scopes;
use App\Services\Connector\ToolEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Settings → Connector: the address AI hosts connect to, the apps that are
 * connected (with revoke) and the instructions.
 */
class ConnectorSettingsController extends Controller
{
    public function index(Request $request, ToolEngine $engine): Response
    {
        $user = $request->user();

        $connections = ConnectorConnection::with(['user:id,name,email', 'client:id,registration_type,metadata_url'])
            ->whereIn('user_id', $this->visibleUserIds($user))
            ->active()
            // A connection whose refresh token ran out can no longer be used; it is
            // cleaned up by connector:prune and not shown as connected.
            ->whereHas('tokens', fn ($query) => $query->whereNull('revoked_at')->where('refresh_expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ConnectorConnection $connection) => [
                'id' => $connection->id,
                'name' => $connection->client_name,
                'verified_host' => $connection->client?->verifiedHost(),
                'scopes' => $connection->scopes ?? [],
                'full_access' => array_diff(Scopes::all(), $connection->scopes ?? []) === [],
                'created_at' => DateHelper::formatFullForUser($connection->created_at),
                'last_used_at' => DateHelper::formatFullForUser($connection->last_used_at),
                'is_own' => $connection->user_id === $user->id,
                'user' => [
                    'name' => $connection->user?->name,
                    'email' => $connection->user?->email,
                ],
            ]);

        try {
            $toolCount = count($engine->tools());
        } catch (RuntimeException) {
            $toolCount = 0;
        }

        return Inertia::render('Settings/Connector/Index', [
            'enabled' => (bool) config('connector.enabled'),
            'connectorUrl' => ConnectorUrls::resource(),
            'isHttps' => $request->isSecure(),
            'isLocalHost' => in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true),
            'toolCount' => $toolCount,
            'connections' => $connections,
            'scopeGroups' => Scopes::grouped(),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function destroy(Request $request, ConnectorConnection $connection): RedirectResponse
    {
        abort_unless(in_array($connection->user_id, $this->visibleUserIds($request->user()), true), 404);

        $connection->revoke();

        return back()->with('success', __('connector.revoked'));
    }

    /**
     * Whose connections a user sees and may revoke: their own, and for the
     * account owner also those of the team members.
     *
     * @return list<int>
     */
    private function visibleUserIds(User $user): array
    {
        return $user->isAdmin()
            ? [$user->id, ...$user->teamMembers()->pluck('id')->all()]
            : [$user->id];
    }
}
