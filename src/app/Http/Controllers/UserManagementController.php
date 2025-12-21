<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Mail\TeamInvitationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserManagementController extends Controller
{
    /**
     * Display list of team members and pending invitations.
     */
    public function index()
    {
        $user = auth()->user();

        // Only admins can manage users
        if (!$user->isAdmin()) {
            abort(403, 'Tylko administrator może zarządzać użytkownikami.');
        }

        $teamMembers = $user->teamMembers()
            ->with(['sharedLists'])
            ->get()
            ->map(fn($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'created_at' => $member->created_at->format('Y-m-d H:i'),
                'shared_lists_count' => $member->sharedLists->count(),
                'shared_lists' => $member->sharedLists->map(fn($list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'permission' => $list->pivot->permission,
                ]),
            ]);

        $pendingInvitations = $user->invitations()
            ->pending()
            ->latest()
            ->get()
            ->map(fn($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'name' => $inv->name,
                'created_at' => $inv->created_at->format('Y-m-d H:i'),
            ]);

        // Get all lists available for sharing
        $availableLists = $user->contactLists()
            ->with('group')
            ->get()
            ->map(fn($list) => [
                'id' => $list->id,
                'name' => $list->name,
                'group' => $list->group?->name,
                'type' => $list->type,
            ]);

        return Inertia::render('Settings/Users/Index', [
            'teamMembers' => $teamMembers,
            'pendingInvitations' => $pendingInvitations,
            'availableLists' => $availableLists,
        ]);
    }

    /**
     * Send an invitation to a new team member.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Check if user already exists
        if (User::where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => 'Użytkownik z tym adresem email już istnieje.']);
        }

        // Check if invitation already exists
        if ($user->invitations()->where('email', $validated['email'])->pending()->exists()) {
            return back()->withErrors(['email' => 'Zaproszenie dla tego adresu email już zostało wysłane.']);
        }

        // Create invitation
        $invitation = $user->invitations()->create([
            'email' => $validated['email'],
            'name' => $validated['name'],
            'token' => TeamInvitation::generateToken(),
        ]);

        // Send email notification
        Mail::to($validated['email'])->send(new TeamInvitationMail($invitation));

        return back()->with('success', 'Zaproszenie zostało wysłane.');
    }

    /**
     * Create user directly (without email invitation).
     */
    public function createUser(Request $request)
    {
        $admin = auth()->user();

        if (!$admin->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'list_permissions' => ['nullable', 'array'],
            'list_permissions.*.list_id' => ['required', 'exists:contact_lists,id'],
            'list_permissions.*.permission' => ['required', 'in:view,edit'],
        ]);

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'admin_user_id' => $admin->id,
        ]);

        // Set list permissions if provided
        if (!empty($validated['list_permissions'])) {
            foreach ($validated['list_permissions'] as $perm) {
                $user->sharedLists()->attach($perm['list_id'], [
                    'permission' => $perm['permission'],
                    'granted_by' => $admin->id,
                ]);
            }
        }

        return back()->with('success', 'Użytkownik został dodany do zespołu.');
    }

    /**
     * Accept an invitation and create user account.
     */
    public function acceptInvitation(Request $request, string $token)
    {
        $invitation = TeamInvitation::where('token', $token)->pending()->firstOrFail();

        // If user is not logged in, show registration form
        if (!auth()->check()) {
            return Inertia::render('Auth/AcceptInvitation', [
                'invitation' => [
                    'email' => $invitation->email,
                    'name' => $invitation->name,
                    'admin_name' => $invitation->admin->name,
                ],
                'token' => $token,
            ]);
        }

        // User is logged in - this shouldn't happen normally
        return redirect()->route('dashboard');
    }

    /**
     * Complete invitation acceptance with password.
     */
    public function completeInvitation(Request $request, string $token)
    {
        $invitation = TeamInvitation::where('token', $token)->pending()->firstOrFail();

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Create user
        $user = User::create([
            'name' => $invitation->name,
            'email' => $invitation->email,
            'password' => Hash::make($validated['password']),
            'admin_user_id' => $invitation->admin_user_id,
            'email_verified_at' => now(),
        ]);

        // Mark invitation as accepted
        $invitation->markAsAccepted();

        // Log the user in
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', 'Witaj w zespole! Twoje konto zostało utworzone.');
    }

    /**
     * Update list permissions for a team member.
     */
    public function updatePermissions(Request $request, User $user)
    {
        $admin = auth()->user();

        if (!$admin->isAdmin()) {
            abort(403);
        }

        // Ensure the user belongs to this admin's team
        if ($user->admin_user_id !== $admin->id) {
            abort(403, 'Ten użytkownik nie należy do Twojego zespołu.');
        }

        $validated = $request->validate([
            'list_permissions' => ['required', 'array'],
            'list_permissions.*.list_id' => ['required', 'exists:contact_lists,id'],
            'list_permissions.*.permission' => ['required', 'in:view,edit'],
        ]);

        // Verify all lists belong to admin
        $adminListIds = $admin->contactLists()->pluck('id')->toArray();
        $requestedListIds = collect($validated['list_permissions'])->pluck('list_id')->toArray();

        foreach ($requestedListIds as $listId) {
            if (!in_array($listId, $adminListIds)) {
                return back()->withErrors(['list_permissions' => 'Nie masz uprawnień do jednej lub więcej z wybranych list.']);
            }
        }

        // Sync permissions
        $syncData = [];
        foreach ($validated['list_permissions'] as $perm) {
            $syncData[$perm['list_id']] = [
                'permission' => $perm['permission'],
                'granted_by' => $admin->id,
            ];
        }

        $user->sharedLists()->sync($syncData);

        return back()->with('success', 'Uprawnienia zostały zaktualizowane.');
    }

    /**
     * Remove a team member.
     */
    public function destroy(User $user)
    {
        $admin = auth()->user();

        if (!$admin->isAdmin()) {
            abort(403);
        }

        // Ensure the user belongs to this admin's team
        if ($user->admin_user_id !== $admin->id) {
            abort(403, 'Ten użytkownik nie należy do Twojego zespołu.');
        }

        // Remove list permissions
        $user->sharedLists()->detach();

        // Delete user
        $user->delete();

        return back()->with('success', 'Użytkownik został usunięty z zespołu.');
    }

    /**
     * Cancel a pending invitation.
     */
    public function cancelInvitation(TeamInvitation $invitation)
    {
        $admin = auth()->user();

        if (!$admin->isAdmin()) {
            abort(403);
        }

        if ($invitation->admin_user_id !== $admin->id) {
            abort(403);
        }

        $invitation->delete();

        return back()->with('success', 'Zaproszenie zostało anulowane.');
    }
}
