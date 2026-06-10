<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Machine-readable build spec so AI models can scaffold correct Convoro themes
 * and extensions on their own — manifest schema, slots, theme tokens, the
 * window.Convoro API, and the GitHub publish flow. Convoro is built to be
 * AI-friendly: point your model at /llms.txt or /api/ai/spec.json.
 */
class AiSpecController extends Controller
{
    public static function spec(): array
    {
        return [
            'name' => 'Convoro extension & theme build spec',
            'version' => (string) config('convoro.version'),
            'summary' => 'Convoro is a Laravel + Inertia/Vue community platform. Extensions are public GitHub repos linked into the catalog; themes restyle via CSS design tokens. No zip uploads, no Composer required for the basic path.',
            'docs' => url('/docs/extensions.html'),
            'manifest' => [
                'file' => 'extension.json (at repo root)',
                'required' => ['id', 'name'],
                'fields' => [
                    'id' => 'unique, folder-safe (e.g. acme-hello)',
                    'name' => 'display name',
                    'version' => 'semver, e.g. 1.0.0',
                    'description' => 'short description',
                    'author' => 'author name',
                    'convoro' => 'version constraint, e.g. ">=0.1.0" (*, ^, ~, comparators supported)',
                    'type' => 'extension | theme',
                    'namespace' => 'PSR-4 root for src/, e.g. "Acme\\\\Hello\\\\"',
                    'provider' => 'FQCN of a Laravel ServiceProvider in src/ (optional)',
                    'migrations' => 'dir of Laravel migrations, run on enable (optional)',
                    'permissions' => 'array of {key,label,category,baseline} merged into the group editor',
                    'settings' => 'array of {key,label,type,default,help,options} rendered as a settings form',
                    'assets' => '{ "forum": "assets/forum.js", "admin": "assets/admin.js" } — prebuilt ESM, injected for enabled extensions',
                    'admin_url' => 'path to an admin management page (optional)',
                ],
                'settings_field_types' => ['text', 'textarea', 'boolean', 'number', 'select (with options[])', 'color'],
            ],
            'service_provider' => [
                'base' => 'Illuminate\\Support\\ServiceProvider',
                'note' => 'boot() runs in the normal lifecycle; register routes/bindings/events. Routes registered in boot() are picked up by route:cache.',
                'read_setting' => "App\\Support\\ExtensionManager::setting('your-id', 'key', \$default)",
                'permission_check' => "\$user->hasPermission('your.key')",
            ],
            'frontend' => [
                'global' => 'window.Convoro (v1)',
                'register' => "window.Convoro.registerSlot(name, { mount(el, ctx){...} | html | component, order, ext })",
                'events' => 'window.Convoro.on(event, cb); window.Convoro.emit(event, payload)',
                'slots' => [
                    'header:end' => 'forum header, right side',
                    'forum:footer' => 'below every forum page',
                    'forum:sidebar' => 'right rail on the community index (widgets)',
                    'topic:below' => 'under the opening post; ctx = { topicId, slug }',
                ],
                'asset_note' => 'Ship a PREBUILT ESM file (no server build). Served from /ext-asset/{id}/{surface}.',
            ],
            'theme_tokens' => [
                'note' => 'Themes set CSS custom properties. Inline styles in extension JS MUST use these var names (NOT Tailwind utility names). [data-theme="dark"] overrides them.',
                'vars' => [
                    '--c-primary' => 'brand color (space-separated RGB, use rgb(var(--c-primary)))',
                    '--c-surface' => 'card background',
                    '--c-surface-2' => 'subtle background',
                    '--c-text' => 'primary text (Tailwind: text-ink)',
                    '--c-text-2' => 'secondary text (text-ink-2)',
                    '--c-muted' => 'muted text (text-ink-muted)',
                    '--c-border' => 'borders (border-line)',
                    '--c-radius' => 'corner radius',
                    '--c-avatar-radius' => 'avatar shape',
                ],
            ],
            'publish' => [
                'steps' => [
                    'Put the extension in a PUBLIC GitHub repo with extension.json at the root.',
                    'Cut a GitHub release (tag) per version — Convoro installs the latest release, falling back to the default branch.',
                    'Link the repo in the Convoro catalog (Admin → Store → Publish from GitHub, or submit to convoro.co).',
                    'It appears in every site\'s Admin → Marketplace catalog; one-click install pulls the release archive from GitHub.',
                ],
                'install_paths' => ['catalog (GitHub)', 'license key (premium)', 'Composer (optional)'],
            ],
            'examples' => [
                'minimal_widget' => "window.Convoro.registerSlot('forum:sidebar', { ext:'acme-hello', mount(el){ el.innerHTML = '<div style=\"color:rgb(var(--c-text))\">Hello</div>'; } });",
            ],
        ];
    }

    public function json(): JsonResponse
    {
        return response()->json(self::spec());
    }

    public function llms(): Response
    {
        $s = self::spec();
        $slots = collect($s['frontend']['slots'])->map(fn ($d, $k) => "- `{$k}` — {$d}")->implode("\n");
        $vars = collect($s['theme_tokens']['vars'])->map(fn ($d, $k) => "- `{$k}` — {$d}")->implode("\n");
        $fields = collect($s['manifest']['fields'])->map(fn ($d, $k) => "- `{$k}`: {$d}")->implode("\n");
        $steps = collect($s['publish']['steps'])->map(fn ($x, $i) => ($i + 1).'. '.$x)->implode("\n");
        $docs = $s['docs'];
        $specUrl = url('/api/ai/spec.json');

        $body = <<<TXT
# Convoro — building themes & extensions with AI

Convoro is AI-friendly by design. This file (and {$specUrl}) is everything an AI model needs to scaffold a correct Convoro extension or theme. Full docs: {$docs}

## What Convoro is
{$s['summary']}

## Extension manifest (extension.json at repo root)
Required: id, name.
{$fields}
Settings field types: {$specUrl} → manifest.settings_field_types.

## Backend (optional service provider)
Extend Illuminate\\Support\\ServiceProvider. boot() registers routes/events (cacheable). Read settings: ExtensionManager::setting('id','key',\$default). Permissions: \$user->hasPermission('key').

## Frontend — window.Convoro
Register UI into named slots with a prebuilt ESM file (assets.forum / assets.admin):
window.Convoro.registerSlot(name, { mount(el, ctx){…} | html | component, order, ext })
Slots:
{$slots}

## Theme tokens (use these CSS vars, never Tailwind names; dark mode overrides them)
{$vars}

## Publishing
{$steps}

## Minimal widget example
{$s['examples']['minimal_widget']}
TXT;

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
