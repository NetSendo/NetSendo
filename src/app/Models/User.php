<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
     * Check if user has 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_secret;
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
}

