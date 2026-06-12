<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * GDPR / right-to-erasure helpers. Two ways to honour a deletion request:
 *
 *   anonymize()  Scrubs every piece of personal data from the account but keeps
 *                the member's posts and topics intact, reattributed to a
 *                "Deleted user" tombstone. The community's discussions stay
 *                readable while the person becomes unidentifiable. Preferred.
 *
 *   erase()      Hard-deletes the account AND everything they authored — their
 *                topics (and every reply inside them), posts, reactions and
 *                direct messages. Irreversible and destructive to threads.
 */
class Gdpr
{
    /** Right to erasure, preserving discussions. */
    public static function anonymize(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $counts = ['posts' => $user->posts()->count(), 'topics' => $user->topics()->count()];

            // Strip the IP address recorded on each of their posts.
            if (Schema::hasColumn('posts', 'ip_address')) {
                DB::table('posts')->where('user_id', $user->id)->update(['ip_address' => null]);
            }

            self::dropDeviceData($user);
            $user->groups()->detach();

            // Scrub the account record itself, lock it, and mark it erased.
            $user->forceFill([
                'name' => 'Deleted user',
                'email' => 'deleted-'.$user->id.'-'.Str::lower(Str::random(8)).'@deleted.invalid',
                'password' => bcrypt(Str::random(40)),
                'remember_token' => null,
                'bio' => null,
                'avatar_path' => null,
                'cover_path' => null,
                'registration_ip' => null,
                'last_ip' => null,
                'is_admin' => false,
                'notify_email' => false,
                'email_verified_at' => null,
                'banned_at' => now(),
                'ban_reason' => 'Account erased at the member’s request (GDPR).',
            ])->save();

            return $counts;
        });
    }

    /** Full erasure — remove the account and all of its authored content. */
    public static function erase(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $counts = ['posts' => $user->posts()->count(), 'topics' => $user->topics()->count()];

            // Deleting a topic cascades to every post within it (FK on topics);
            // then remove their remaining replies in other people's topics.
            $user->topics()->delete();
            $user->posts()->delete();

            self::dropDeviceData($user);

            // Deleting the user cascades reactions, direct messages, group
            // membership and the wall posts on/by them (FK cascadeOnDelete).
            $user->delete();

            return $counts;
        });
    }

    /** Remove sessions, push subscriptions and API tokens (device-level PII). */
    private static function dropDeviceData(User $user): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $user->pushSubscriptions()->delete();
        $user->tokens()->delete();
    }
}
