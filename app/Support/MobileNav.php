<?php

namespace App\Support;

/**
 * Configurable mobile bottom tab bar. Admins choose which tabs appear and in
 * what order (Admin → Settings); the frontend (MobileTabBar.vue) owns the icons,
 * labels and routes — this just validates + shares the enabled key list.
 */
class MobileNav
{
    /** Every tab the admin can switch on. */
    public const CATALOG = ['home', 'search', 'compose', 'notifications', 'messages', 'members', 'leaderboard', 'account'];

    /** Sensible out-of-the-box bar. */
    public const DEFAULTS = ['home', 'search', 'compose', 'notifications', 'account'];

    public static function enabled(): bool
    {
        return (bool) Settings::get('mobile.nav_enabled', true);
    }

    /** @return string[] ordered, validated, enabled tab keys */
    public static function tabs(): array
    {
        $saved = Settings::get('mobile.tabs', null);
        if (is_string($saved)) {
            $saved = json_decode($saved, true);
        }
        if (! is_array($saved)) {
            return self::DEFAULTS;
        }
        $tabs = array_values(array_filter($saved, fn ($k) => in_array($k, self::CATALOG, true)));

        return $tabs ?: self::DEFAULTS;
    }

    public static function share(): array
    {
        return [
            'enabled' => self::enabled(),
            'tabs' => self::tabs(),
            'catalog' => self::CATALOG,
        ];
    }
}
