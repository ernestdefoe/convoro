<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's chosen primary social group — the badge shown beside their name.
 *
 * @property int $user_id
 * @property int|null $social_group_id
 */
class SocialGroupPrimary extends Model
{
    protected $table = 'social_group_primary';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SocialGroup::class, 'social_group_id');
    }
}
