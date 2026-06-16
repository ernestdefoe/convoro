<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $social_group_id
 * @property int $user_id
 * @property string $role
 * @property \Illuminate\Support\Carbon|null $banned_at
 */
class SocialGroupMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'joined_at' => 'datetime',
        'banned_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SocialGroup::class, 'social_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
