<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value site settings with a forever-cache (flushed on write). Values are
 * JSON-encoded so booleans/numbers round-trip. Cache store is database on shared
 * hosting, so this needs no Redis.
 */
class Settings
{
    private const CACHE_KEY = 'convoro.settings';

    /** Defaults — also the source of truth for which keys exist. */
    public const DEFAULTS = [
        'site.name' => 'Convoro',
        'site.tagline' => 'A modern community',
        'site.logo' => '',                   // header logo image URL (empty = default mark)
        'forum.default_view' => 'feed',     // feed | grid
        'realtime.enabled' => false,         // off by default (shared-hosting baseline)
        'digests.enabled' => true,           // master switch for digest emails
        'pwa.banner' => true,                // show the "install app" banner
        'pwa.short_name' => 'Convoro',       // PWA short name
        'fa.kit_url' => '',                  // optional Font Awesome Kit script URL (Pro/custom icons)
        'seo.description' => '',             // default meta description (falls back to tagline)
        'seo.image' => '',                  // default social share image (falls back to logo)
        'stripe.key' => '',                  // Stripe publishable key (central store)
        'stripe.secret' => '',               // Stripe secret key (manual / platform fallback)
        'stripe.webhook_secret' => '',       // Stripe webhook signing secret
        'stripe.account_id' => '',           // Connect: linked account id (acct_…)
        'stripe.access_token' => '',         // Connect: linked account secret (from OAuth)
        'stripe.connected_publishable' => '', // Connect: linked account publishable key
        // Outgoing mail. transport: 'sendmail' (server/PHP mail — works on most shared hosts,
        // no config) or 'smtp'. When unset, falls back to the .env mailer.
        'mail.configured' => false,          // once true, these override .env
        'mail.transport' => 'sendmail',      // sendmail | smtp
        'mail.from_address' => '',           // e.g. community@example.com
        'mail.from_name' => '',              // defaults to site name when blank
        'mail.smtp_host' => '',
        'mail.smtp_port' => 587,
        'mail.smtp_username' => '',
        'mail.smtp_password' => '',
        'mail.smtp_encryption' => 'tls',     // tls | ssl | none
        'theme.primary' => '#5b5bd6',
        'theme.radius' => 12,
        'theme.mode' => 'light',             // light | dark
        'theme.font' => 'Inter',             // see Theme::FONTS
        'theme.font_size' => 16,             // base px
        'theme.container' => 1240,           // content max width px (0 = full width)
        'theme.avatar_shape' => 'circle',    // circle | rounded | square
        'theme.post_style' => 'card',        // card | bordered | flat
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->pluck('value', 'key')
                ->map(fn ($v) => json_decode((string) $v, true))
                ->all();
        });

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @param array<string, mixed> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => json_encode($v)]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** Only the public-safe subset shared to the frontend. */
    public static function public(): array
    {
        return [
            'name' => self::get('site.name'),
            'tagline' => self::get('site.tagline'),
            'logo' => self::get('site.logo'),
            'defaultView' => self::get('forum.default_view'),
            'realtime' => (bool) self::get('realtime.enabled'),
            'pwaBanner' => (bool) self::get('pwa.banner'),
            'pwaShortName' => self::get('pwa.short_name'),
            'theme' => [
                'primary' => self::get('theme.primary'),
                'radius' => (int) self::get('theme.radius'),
                'mode' => self::get('theme.mode'),
                'font' => self::get('theme.font'),
                'fontSize' => (int) self::get('theme.font_size'),
                'container' => (int) self::get('theme.container'),
                'avatarShape' => self::get('theme.avatar_shape'),
                'postStyle' => self::get('theme.post_style'),
            ],
        ];
    }
}
