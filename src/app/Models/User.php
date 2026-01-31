<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use App\Services\Mail\SystemMailService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
        'settings',
        'admin_user_id',
        'referred_by_affiliate_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Auto-seed Lead Scoring rules for new admin users
        static::created(function (User $user) {
            // Only seed for admin users (not team members)
            if ($user->isAdmin()) {
                try {
                    LeadScoringRule::seedDefaultsForUser($user->id);
                    \Log::info("Lead Scoring rules seeded for new user {$user->id}");
                } catch (\Exception $e) {
                    \Log::error("Failed to seed Lead Scoring rules for user {$user->id}: " . $e->getMessage());
                }
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('timezone')) {
                event(new \App\Events\UserTimezoneUpdated($user, $user->getOriginal('timezone')));
            }
        });
    }

    /**
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_secret;
    }

    /**
     * Get the admin who invited this user (null if user is admin).
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Get team members invited by this admin.
     */
    public function teamMembers()
    {
        return $this->hasMany(User::class, 'admin_user_id');
    }

    /**
     * Get pending invitations sent by this admin.
     */
    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class, 'admin_user_id');
    }

    /**
     * Get lists shared with this user by admin.
     */
    public function sharedLists()
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_user')
            ->withPivot(['permission', 'granted_by'])
            ->withTimestamps();
    }

    /**
     * Check if this user is an admin (not invited by anyone).
     */
    public function isAdmin(): bool
    {
        return is_null($this->admin_user_id);
    }

    /**
     * Get the admin user ID for this account.
     * Returns own ID if admin, or admin_user_id if team member.
     */
    public function getAdminUserId(): int
    {
        return $this->admin_user_id ?? $this->id;
    }

    /**
     * Get the admin user for this account.
     */
    public function getAdminUser(): User
    {
        return $this->isAdmin() ? $this : $this->admin;
    }

    /**
     * Get all contact lists accessible by this user.
     * Admin sees all lists, team members see their own + shared lists.
     */
    public function accessibleLists()
    {
        if ($this->isAdmin()) {
            // Admin sees all lists created by them or their team members
            $teamMemberIds = $this->teamMembers()->pluck('id')->toArray();
            $allUserIds = array_merge([$this->id], $teamMemberIds);

            return ContactList::whereIn('user_id', $allUserIds);
        }

        // Team member sees their own lists + shared lists
        $ownListIds = $this->contactLists()->pluck('id');
        $sharedListIds = $this->sharedLists()->pluck('contact_lists.id');
        $allListIds = $ownListIds->merge($sharedListIds)->unique();

        return ContactList::whereIn('id', $allListIds);
    }

    /**
     * Check if user can access a specific list.
     */
    public function canAccessList(ContactList $list): bool
    {
        if ($this->isAdmin()) {
            // Admin can access lists owned by self or team members
            $teamMemberIds = $this->teamMembers()->pluck('id')->toArray();
            $allUserIds = array_merge([$this->id], $teamMemberIds);
            return in_array($list->user_id, $allUserIds);
        }

        // Team member can access own lists or shared lists
        if ($list->user_id === $this->id) {
            return true;
        }

        return $this->sharedLists()->where('contact_lists.id', $list->id)->exists();
    }

    /**
     * Check if user can edit a specific list.
     */
    public function canEditList(ContactList $list): bool
    {
        if ($this->isAdmin()) {
            return $this->canAccessList($list);
        }

        // Own list
        if ($list->user_id === $this->id) {
            return true;
        }

        // Shared list with edit permission
        return $this->sharedLists()
            ->where('contact_lists.id', $list->id)
            ->wherePivot('permission', 'edit')
            ->exists();
    }

    /**
     * Get the contact lists for the user.
     */
    public function contactLists()
    {
        return $this->hasMany(ContactList::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    public function contactListGroups()
    {
        return $this->hasMany(ContactListGroup::class);
    }

    public function tags()
    {
        return $this->hasMany(Tag::class);
    }

    public function templateBlocks()
    {
        return $this->hasMany(TemplateBlock::class);
    }

    public function mailboxes()
    {
        return $this->hasMany(Mailbox::class);
    }

    public function smsProviders()
    {
        return $this->hasMany(SmsProvider::class);
    }

    public function externalPages()
    {
        return $this->hasMany(ExternalPage::class);
    }

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * Get the lead scoring rules for the user.
     */
    public function leadScoringRules()
    {
        return $this->hasMany(LeadScoringRule::class);
    }

    /**
     * Get the affiliate who referred this user.
     */
    public function referredByAffiliate()
    {
        return $this->belongsTo(Affiliate::class, 'referred_by_affiliate_id');
    }

    /**
     * Send the password reset notification.
     * Overrides default to use SystemMailService for ENV/Mailbox fallback.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        // Prepare system mail service (configures Laravel mailer)
        $mailService = app(SystemMailService::class);

        if (!$mailService->prepare()) {
            \Log::error('Cannot send password reset email: No mail configuration available', [
                'user_id' => $this->id,
                'email' => $this->email,
            ]);
            return;
        }

        $this->notify(new ResetPasswordNotification($token));
    }
}

