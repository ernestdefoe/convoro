<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;

/**
 * 0.37 — Sidebar widgets become extensions.
 *
 * The forum sidebar moved from a hardcoded built-in widget list
 * ([{id,type,title,body}]) to first-party widget extensions ordered by an
 * [{key,enabled}] layout. This rewrites any saved legacy layout into the new
 * shape and rescues the custom Text/HTML widget's content into the
 * "About / Custom HTML" widget's settings so nothing is lost on upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        $raw = json_decode((string) Settings::get('widgets.sidebar', ''), true);
        if (! is_array($raw) || $raw === []) {
            return; // nothing saved → the default layout applies, no work needed
        }

        // Rescue the first legacy text widget's content into the About widget.
        foreach ($raw as $w) {
            if (is_array($w) && ($w['type'] ?? null) === 'text') {
                if (! empty($w['body']) && Settings::get('widgets.about_html', '') === '') {
                    Settings::set('widgets.about_html', (string) $w['body']);
                }
                if (! empty($w['title']) && Settings::get('widgets.about_title', '') === '') {
                    Settings::set('widgets.about_title', (string) $w['title']);
                }
                break;
            }
        }

        // Persist the normalized [{key,enabled}] layout, enabling any legacy
        // About content we just rescued.
        $layout = Settings::normalizeWidgetLayout($raw);
        $hasAbout = (string) Settings::get('widgets.about_html', '') !== '';
        $layout = array_map(function ($entry) use ($hasAbout) {
            if ($entry['key'] === 'convoro-about' && $hasAbout) {
                $entry['enabled'] = true;
            }

            return $entry;
        }, $layout);

        Settings::set('widgets.sidebar', json_encode(array_values($layout)));
    }

    public function down(): void
    {
        // One-way data normalization; the legacy shape is not restored.
    }
};
