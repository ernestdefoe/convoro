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
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'digest_frequency', 'is_admin', 'bio', 'avatar_path', 'cover_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

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
