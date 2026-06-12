<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\VAPID;

/**
 * VAPID key management for browser push.
 *
 * Keys can live in .env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY) OR be generated
 * and stored in Settings from the admin UI. apply() lets the stored keys win at
 * runtime so a community can rotate them without touching the server — handy if
 * the keys ever get corrupted or need rolling.
 */
class WebPush
{
    /** Let admin-stored VAPID keys override the .env defaults for this request/job. */
    public static function apply(): void
    {
        try {
            $pub = Settings::get('webpush.vapid_public');
            $priv = Settings::get('webpush.vapid_private');
            if ($pub && $priv) {
                config(['webpush.vapid.public_key' => $pub, 'webpush.vapid.private_key' => $priv]);
            }
            if ($subject = Settings::get('webpush.vapid_subject')) {
                config(['webpush.vapid.subject' => $subject]);
            }
        } catch (\Throwable) {
            // DB not ready (install/migrate) — fall back to .env config.
        }
    }

    public static function configured(): bool
    {
        return (bool) config('webpush.vapid.public_key') && (bool) config('webpush.vapid.private_key');
    }

    public static function publicKey(): string
    {
        return (string) config('webpush.vapid.public_key');
    }

    public static function subject(): string
    {
        return (string) (config('webpush.vapid.subject') ?: 'mailto:admin@'.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com'));
    }

    /**
     * Generate a fresh VAPID keypair, store it, and invalidate every existing
     * push subscription (they were created against the old public key and can no
     * longer receive pushes). Returns how many subscriptions were cleared.
     */
    public static function regenerate(?string $subject = null): int
    {
        $keys = VAPID::createVapidKeys(); // ['publicKey' => …, 'privateKey' => …]
        Settings::setMany([
            'webpush.vapid_public' => $keys['publicKey'],
            'webpush.vapid_private' => $keys['privateKey'],
            'webpush.vapid_subject' => $subject ?: self::subject(),
        ]);
        config(['webpush.vapid.public_key' => $keys['publicKey'], 'webpush.vapid.private_key' => $keys['privateKey']]);

        $cleared = (int) DB::table('push_subscriptions')->count();
        DB::table('push_subscriptions')->delete();

        return $cleared;
    }
}
