<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'digest_frequency', 'is_admin', 'bio', 'avatar_path', 'cover_path', 'registration_ip', 'last_ip', 'banned_at', 'ban_reason'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /** Whether this account is currently banned. */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /** Topics started by this user. */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    /** Forum posts authored by this user. */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** Wall posts left ON this user's profile. */
    public function profilePosts(): HasMany
    {
        return $this->hasMany(ProfilePost::class, 'profile_user_id');
    }

    /** Groups this user belongs to. */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')->withTimestamps();
    }

    /** Effective permission check: admins bypass; baseline for all; else via groups. */
    public function hasPermission(string $key): bool
    {
        if ($this->is_admin) {
            return true;
        }
        if (in_array($key, \App\Support\Permissions::baseline(), true)) {
            return true;
        }

        return $this->groups->contains(fn ($g) => in_array($key, (array) $g->permissions, true));
    }

    /** Direct-message conversations this user is part of. */
    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

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
            'last_digest_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }
}
