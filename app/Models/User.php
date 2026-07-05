<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'digest_frequency', 'notify_email', 'locale', 'auto_translate', 'is_admin', 'bio', 'avatar_path', 'cover_path', 'registration_ip', 'last_ip', 'banned_at', 'ban_reason', 'invited_by', 'trust_level', 'trust_locked', 'trust_promoted_at'])]
#[Hidden(['password', 'remember_token', 'github_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

    /** Whether this account is currently banned. */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Sanitize the display name on every write (strip control/invisible chars,
     * NFC-normalize, collapse whitespace) so broken names never reach storage.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => \App\Support\Username::sanitize($value),
        );
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

    /** Social-group memberships (distinct from permission groups above). */
    public function socialGroupMemberships(): HasMany
    {
        return $this->hasMany(SocialGroupMember::class);
    }

    /** Social groups this user is an active (non-banned) member of. */
    public function socialGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SocialGroup::class, 'social_group_members')
            ->withPivot(['role', 'banned_at'])
            ->wherePivotNull('banned_at')
            ->withTimestamps();
    }

    /** The member's chosen primary social group (the badge on their profile). */
    public function primarySocialGroup(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\SocialGroupPrimary::class);
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
            'notify_email' => 'boolean',
            'notification_prefs' => 'array',
            'auto_translate' => 'boolean',
            'is_federated' => 'boolean',
            'trust_level' => 'integer',
            'trust_locked' => 'boolean',
            'trust_promoted_at' => 'datetime',
            'github_token' => 'encrypted',
        ];
    }

    /** Whether this seller has connected their GitHub account. */
    public function hasGithub(): bool
    {
        return filled($this->github_token);
    }

    /**
     * Whether mail can actually be delivered to this account. Synthetic / system
     * accounts use reserved, non-routable placeholder domains — the AI assistant
     * (assistant@convoro.local) and federated actors (…@federated.invalid) — which
     * have no MX and only bounce. Skip all outbound mail to those.
     */
    public function isMailable(): bool
    {
        $email = (string) $this->email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $domain = strtolower((string) substr(strrchr($email, '@'), 1));
        if ($domain === '' || $domain === 'localhost') {
            return false;
        }
        // RFC 2606 / 6762 reserved, non-routable TLDs used for placeholders.
        $tld = (string) strrchr($domain, '.');

        return ! in_array($tld, ['.local', '.invalid', '.localhost', '.internal', '.example', '.test'], true);
    }

    /** Notification types a member can tune per channel. */
    public const NOTIFY_TYPES = ['reply', 'mention', 'message', 'reaction', 'wall', 'badge', 'event'];

    /**
     * Does this member want a notification of $type over $channel ('email'|'push')?
     * Falls back to sensible defaults when they haven't set a preference: push on
     * for everything; email on for everything except the noisy reaction/badge
     * types, and never any email if their master email switch is off.
     */
    public function wantsNotification(string $type, string $channel): bool
    {
        $prefs = $this->notification_prefs ?? [];
        if (isset($prefs[$type][$channel])) {
            return (bool) $prefs[$type][$channel];
        }
        if ($channel === 'push') {
            return true;
        }
        // email defaults
        if (($this->notify_email ?? true) === false) {
            return false;
        }

        return ! in_array($type, ['reaction', 'badge'], true);
    }
}
