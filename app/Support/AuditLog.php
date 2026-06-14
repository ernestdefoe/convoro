<?php

namespace App\Support;

use App\Models\Group;
use App\Models\IpBan;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\Report;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Records staff/admin actions to the append-only moderation_logs table.
 *
 * Call from any moderation/admin action:
 *
 *   AuditLog::record('post.delete', $post, subject: $post->user, reason: $reason);
 *   AuditLog::record('user.ban', $user, subject: $user, meta: ['ban_ip' => true]);
 *   AuditLog::record('ip.ban', target: ['ip', $ip, $ip]);
 *
 * The actor (current user), their name, and request IP are captured
 * automatically; target type/id/label are snapshotted from the model so the
 * entry stays readable after the target is deleted.
 */
class AuditLog
{
    public static function record(
        string $action,
        mixed $target = null,
        ?User $subject = null,
        ?string $reason = null,
        array $meta = [],
    ): void {
        $actor = Auth::user();
        [$type, $id, $label] = self::describe($target);

        try {
            ModerationLog::create([
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'action' => $action,
                'target_type' => $type,
                'target_id' => $id,
                'target_label' => $label,
                'subject_user_id' => $subject?->id,
                'reason' => $reason !== null && $reason !== '' ? Str::limit($reason, 1000, '') : null,
                'meta' => $meta ?: null,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Auditing must never break the action it records.
        }
    }

    /**
     * Derive [type, id, label] from a target, which may be a model, a
     * [type, id, label] array, or null.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private static function describe(mixed $target): array
    {
        if ($target === null) {
            return [null, null, null];
        }

        if (is_array($target)) {
            return [$target[0] ?? null, isset($target[1]) ? (string) $target[1] : null, $target[2] ?? null];
        }

        if ($target instanceof Post) {
            $excerpt = Str::limit(trim(strip_tags((string) $target->body_html)), 80);

            return ['post', (string) $target->id, $excerpt !== '' ? $excerpt : __('(empty post)')];
        }

        if ($target instanceof Topic) {
            return ['topic', (string) $target->id, Str::limit((string) $target->title, 100)];
        }

        if ($target instanceof User) {
            return ['user', (string) $target->id, (string) $target->name];
        }

        if ($target instanceof IpBan) {
            return ['ip', (string) $target->ip_address, (string) $target->ip_address];
        }

        if ($target instanceof Report) {
            return ['report', (string) $target->id, Str::limit((string) ($target->reason ?? ''), 80)];
        }

        if ($target instanceof Group) {
            return ['group', (string) $target->id, (string) $target->name];
        }

        if ($target instanceof Model) {
            return [Str::snake(class_basename($target)), (string) $target->getKey(), null];
        }

        return [null, null, (string) $target];
    }
}
