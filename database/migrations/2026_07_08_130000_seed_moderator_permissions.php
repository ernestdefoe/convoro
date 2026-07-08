<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the seeded Moderator group a sensible default moderation toolkit so the
 * new delegable-moderation permissions work out of the box. Only applied when
 * the group has no permissions yet, so existing hand-tuned setups are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mod = DB::table('groups')->where('key', 'moderator')->first();
        if (! $mod) {
            return;
        }

        $current = json_decode((string) $mod->permissions, true) ?: [];
        if ($current !== []) {
            return;
        }

        $defaults = [
            'post.edit_any', 'post.delete_any', 'post.move', 'post.approve',
            'topic.rename_any', 'topic.move', 'topic.delete_any', 'topic.lock', 'topic.pin',
            'user.ban', 'group.moderate',
        ];

        DB::table('groups')->where('id', $mod->id)->update(['permissions' => json_encode($defaults)]);
    }

    public function down(): void
    {
        // Non-destructive: leave the granted permissions in place.
    }
};
