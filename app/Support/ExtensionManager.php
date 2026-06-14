<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Convoro's extension system. Extensions are self-contained packages that drop
 * into a directory — NO Composer, NO `composer dump-autoload`, NO shell — so the
 * whole thing works on cheap shared hosting where the only way in is uploading a
 * zip through the admin Marketplace.
 *
 * Two roots are scanned:
 *   - base_path('extensions')          → first-party / bundled-with-release
 *   - storage_path('app/extensions')   → uploaded via the Marketplace (writable,
 *                                         survives self-updates since they skip storage/)
 *
 * A package is a folder containing `extension.json` (see manifest() for the
 * schema) plus optional src/ (PHP, PSR-4 autoloaded here), migrations/, and
 * prebuilt assets/ (forum.js / admin.js ESM bundles — shipped built, never
 * compiled on the server).
 */
class ExtensionManager
{
    private const ENABLED_KEY = 'extensions.enabled';

    /** @var array<string,array>|null in-process cache of discovered manifests, keyed by id */
    private static ?array $cache = null;

    /** Directories scanned for extensions, lowest→highest precedence. */
    public static function roots(): array
    {
        return [
            base_path('extensions'),
            storage_path('app/extensions'),
        ];
    }

    /** Absolute path of an installed extension, or null if not present. */
    public static function path(string $id): ?string
    {
        return self::all()[$id]['_path'] ?? null;
    }

    /**
     * Discover every installed extension and return its (validated) manifest,
     * keyed by id. Later roots override earlier ones with the same id.
     *
     * @return array<string,array>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // Shared-host optimization: read a compiled manifest (a plain PHP array,
        // OPcache-friendly) instead of re-scanning the filesystem every request.
        // A cheap mtime signature of the roots auto-invalidates it when an
        // extension is added/removed, so it's self-healing — no stale cache.
        $compiled = self::compiledPath();
        $sig = self::signature();
        if (is_file($compiled)) {
            $data = @include $compiled;
            if (is_array($data) && ($data['__sig'] ?? null) === $sig && isset($data['items']) && is_array($data['items'])) {
                return self::$cache = $data['items'];
            }
        }

        $found = [];
        foreach (self::roots() as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (File::directories($root) as $dir) {
                $manifestFile = $dir.'/extension.json';
                if (! is_file($manifestFile)) {
                    continue;
                }
                $manifest = self::manifest($manifestFile, $dir);
                if ($manifest !== null) {
                    $found[$manifest['id']] = $manifest;
                }
            }
        }

        // Composer-installed extensions (type: convoro-extension). Their classes
        // autoload via Composer itself, so they need no custom autoloader.
        foreach (self::composerPackageDirs() as $dir) {
            if (is_file($dir.'/extension.json') && ($m = self::manifest($dir.'/extension.json', $dir)) !== null) {
                $m['_composer'] = true;
                $found[$m['id']] = $m;
            }
        }

        self::writeCompiled($found, $sig);

        return self::$cache = $found;
    }

    /** Path to the compiled manifest cache (a plain PHP array). */
    private static function compiledPath(): string
    {
        return base_path('bootstrap/cache/convoro-extensions.php');
    }

    /** Cheap fingerprint of the extension roots — changes when one is added/removed. */
    private static function signature(): string
    {
        $parts = [];
        foreach (self::roots() as $root) {
            $parts[] = is_dir($root) ? (string) @filemtime($root) : '0';
        }
        $installed = base_path('vendor/composer/installed.json');
        $parts[] = is_file($installed) ? (string) @filemtime($installed) : '0';

        return implode('-', $parts);
    }

    /** Best-effort write of the compiled manifest. Silently no-ops if not writable. */
    private static function writeCompiled(array $found, string $sig): void
    {
        $file = self::compiledPath();
        if (! is_dir(dirname($file))) {
            return;
        }
        @file_put_contents(
            $file,
            '<?php return '.var_export(['__sig' => $sig, 'items' => $found], true).';'.PHP_EOL,
            LOCK_EX,
        );
    }

    /** Absolute dirs of Composer packages declaring `type: convoro-extension`. */
    private static function composerPackageDirs(): array
    {
        $installed = base_path('vendor/composer/installed.json');
        if (! is_file($installed)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($installed), true);
        $packages = $data['packages'] ?? $data ?? [];
        $vendor = base_path('vendor');

        $dirs = [];
        foreach ($packages as $p) {
            if (($p['type'] ?? null) === 'convoro-extension' && ! empty($p['name'])) {
                // installed.json paths are relative to vendor/composer/.
                $rel = $p['install-path'] ?? ('../'.$p['name']);
                $path = realpath($vendor.'/composer/'.$rel) ?: realpath($vendor.'/'.$p['name']);
                if ($path && is_dir($path)) {
                    $dirs[] = $path;
                }
            }
        }

        return $dirs;
    }

    /** Parse + normalize a manifest file. Returns null if malformed. */
    public static function manifest(string $file, string $dir): ?array
    {
        $raw = json_decode((string) @file_get_contents($file), true);
        if (! is_array($raw) || empty($raw['id']) || empty($raw['name'])) {
            return null;
        }

        return [
            'id' => (string) $raw['id'],
            'name' => (string) $raw['name'],
            'version' => (string) ($raw['version'] ?? '0.0.0'),
            'description' => (string) ($raw['description'] ?? ''),
            'author' => (string) ($raw['author'] ?? ''),
            'convoro' => (string) ($raw['convoro'] ?? '*'),    // version constraint
            'type' => in_array(($raw['type'] ?? 'extension'), ['extension', 'theme'], true) ? ($raw['type'] ?? 'extension') : 'extension',
            'namespace' => isset($raw['namespace']) ? rtrim((string) $raw['namespace'], '\\').'\\' : null,
            'provider' => $raw['provider'] ?? null,            // FQCN of a ServiceProvider
            'migrations' => $raw['migrations'] ?? null,        // dir relative to package
            'permissions' => is_array($raw['permissions'] ?? null) ? $raw['permissions'] : [],
            'settings' => is_array($raw['settings'] ?? null) ? array_values(array_filter($raw['settings'], fn ($f) => is_array($f) && ! empty($f['key']))) : [],
            'assets' => is_array($raw['assets'] ?? null) ? $raw['assets'] : [],
            'core' => (bool) ($raw['core'] ?? false),     // first-party, always-loaded
            'premium' => (bool) ($raw['premium'] ?? false),
            'price' => $raw['price'] ?? 0,
            'admin_url' => isset($raw['admin_url']) && is_string($raw['admin_url']) ? $raw['admin_url'] : null,
            // Server-rendered header nav link {label, href, auth?} so it appears
            // instantly with the page instead of popping in after the JS bundle.
            'nav' => is_array($raw['nav'] ?? null) && ! empty($raw['nav']['href']) && ! empty($raw['nav']['label']) ? [
                'label' => (string) $raw['nav']['label'],
                'href' => (string) $raw['nav']['href'],
                'auth' => (bool) ($raw['nav']['auth'] ?? false),
            ] : null,
            'icon' => $raw['icon'] ?? null,
            '_path' => $dir,
            '_writable' => is_writable($dir),
        ];
    }

    /**
     * The extension's own icon as inline SVG markup (for the admin nav, so every
     * extension shows its own icon instead of a shared fallback glyph). Reads the
     * manifest `icon` file; returns null if missing/unreadable. Lightly stripped
     * of <script> and inline event handlers before it's rendered.
     */
    public static function iconSvgFor(array $manifest): ?string
    {
        $icon = $manifest['icon'] ?? null;
        $dir = $manifest['_path'] ?? null;
        if (! $icon || ! $dir) {
            return null;
        }
        $file = $dir.'/'.basename((string) $icon);
        if (! is_file($file) || ! str_ends_with(strtolower($file), '.svg')) {
            return null;
        }
        $svg = (string) @file_get_contents($file);
        if ($svg === '' || ! str_contains($svg, '<svg')) {
            return null;
        }
        // Defensive strip — first-party + AI-reviewed, but it's rendered via v-html.
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
        $svg = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\')#i', '', $svg);

        return trim($svg);
    }

    /** @return string[] ids of enabled extensions */
    public static function enabledIds(): array
    {
        // Tolerate a not-yet-migrated install (no settings table during early
        // CLI / first migrate) — just report nothing enabled.
        try {
            $ids = Settings::get(self::ENABLED_KEY, []);
        } catch (\Throwable) {
            return [];
        }

        return is_array($ids) ? array_values(array_filter($ids, 'is_string')) : [];
    }

    public static function isEnabled(string $id): bool
    {
        return in_array($id, self::enabledIds(), true);
    }

    /** Enabled extensions that are actually installed, in manifest form. */
    public static function enabled(): array
    {
        $all = self::all();

        return array_values(array_filter(
            array_map(fn ($id) => $all[$id] ?? null, self::enabledIds())
        ));
    }

    /**
     * Header nav links contributed by enabled extensions (manifest `nav`), so
     * they render server-side and appear instantly — no async-bundle flash.
     *
     * @return array<int,array{label:string,href:string,auth:bool}>
     */
    public static function navLinks(): array
    {
        $out = [];
        foreach (self::enabled() as $m) {
            if (! empty($m['nav'])) {
                $out[] = $m['nav'];
            }
        }

        return $out;
    }

    public static function setEnabled(string $id, bool $on): void
    {
        $ids = self::enabledIds();
        if ($on && ! in_array($id, $ids, true)) {
            $ids[] = $id;
        } elseif (! $on) {
            $ids = array_values(array_filter($ids, fn ($x) => $x !== $id));
        }
        Settings::set(self::ENABLED_KEY, array_values($ids));
    }

    public static function flushCache(): void
    {
        self::$cache = null;
        $file = self::compiledPath();
        if (is_file($file)) {
            @unlink($file);
        }
    }

    // -- Boot-time wiring --------------------------------------------------

    /**
     * Register a PSR-4 autoloader for every installed extension's src/ dir, so
     * its classes resolve without Composer ever knowing they exist. Cheap and
     * idempotent; safe to call once on boot.
     */
    public static function registerAutoloader(): void
    {
        $map = [];
        foreach (self::all() as $m) {
            if ($m['namespace'] && is_dir($m['_path'].'/src')) {
                $map[$m['namespace']] = $m['_path'].'/src';
            }
        }
        if (! $map) {
            return;
        }

        spl_autoload_register(function (string $class) use ($map) {
            foreach ($map as $prefix => $base) {
                if (str_starts_with($class, $prefix)) {
                    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
                    $path = $base.'/'.$rel.'.php';
                    if (is_file($path)) {
                        require $path;
                    }

                    return;
                }
            }
        });
    }

    /**
     * Register each ENABLED extension's service provider and migration path.
     * Called from a service provider's register() so providers can bind, and
     * boot() runs in the normal lifecycle.
     */
    public static function bootEnabled(\Illuminate\Contracts\Foundation\Application $app): void
    {
        foreach (self::enabled() as $m) {
            // Service provider (optional). Must be a Laravel ServiceProvider.
            $provider = $m['provider'];
            if (is_string($provider) && class_exists($provider) && is_subclass_of($provider, \Illuminate\Support\ServiceProvider::class)) {
                $app->register($provider);
            }

            // Migrations: make `php artisan migrate` aware of the path. Running
            // them is handled explicitly on enable (see ExtensionInstaller).
            if ($m['migrations'] && is_dir($m['_path'].'/'.$m['migrations'])) {
                $app->afterResolving('migrator', function ($migrator) use ($m) {
                    $migrator->path($m['_path'].'/'.$m['migrations']);
                });
            }
        }
    }

    /**
     * Permission catalog additions contributed by enabled extensions.
     * Shape per entry: ['key'=>..,'label'=>..,'category'=>..,'baseline'=>bool].
     */
    public static function permissionAdditions(): array
    {
        $out = [];
        foreach (self::enabled() as $m) {
            foreach ($m['permissions'] as $p) {
                if (! is_array($p) || empty($p['key'])) {
                    continue;
                }
                $out[] = [
                    'key' => (string) $p['key'],
                    'label' => (string) ($p['label'] ?? $p['key']),
                    'category' => (string) ($p['category'] ?? $m['name']),
                    'baseline' => (bool) ($p['baseline'] ?? false),
                ];
            }
        }

        return $out;
    }

    /**
     * Frontend assets to inject for enabled extensions on a given surface.
     *
     * @param  'forum'|'admin'  $surface
     * @return array<int,array{id:string,url:string}>
     */
    public static function assetsFor(string $surface): array
    {
        $out = [];
        $seen = [];

        // Enabled extensions, plus first-party "core" widgets which always load
        // (their on/off + ordering is governed by the theme editor's layout,
        // not the marketplace enable toggle).
        $list = self::enabled();
        foreach (self::all() as $m) {
            if (! empty($m['core'])) {
                $list[] = $m;
            }
        }

        foreach ($list as $m) {
            if (isset($seen[$m['id']])) {
                continue;
            }
            $seen[$m['id']] = true;
            $file = $m['assets'][$surface] ?? null;
            if (is_string($file) && is_file($m['_path'].'/'.$file)) {
                // Cache-bust by app + extension version (assets are served with a
                // 1-hour max-age), so a widget update propagates on the next load
                // instead of being stuck on a stale cached copy.
                $v = rawurlencode((string) config('convoro.version').'.'.($m['version'] ?? '1'));
                $out[] = ['id' => $m['id'], 'url' => "/ext-asset/{$m['id']}/{$surface}?v={$v}"];
            }
        }

        return $out;
    }

    // -- Per-extension settings -------------------------------------------

    /** The declarative settings schema an extension ships in its manifest. */
    public static function settingsSchema(string $id): array
    {
        return self::all()[$id]['settings'] ?? [];
    }

    /** Namespaced storage key for one extension setting. */
    public static function settingKey(string $id, string $key): string
    {
        return "ext.{$id}.{$key}";
    }

    /**
     * Read one extension setting, falling back to the manifest default. This is
     * what extension code calls at runtime.
     */
    public static function setting(string $id, string $key, mixed $default = null): mixed
    {
        $storeKey = self::settingKey($id, $key);
        $stored = Settings::get($storeKey, '__missing__');
        if ($stored !== '__missing__') {
            return $stored;
        }
        foreach (self::settingsSchema($id) as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field['default'] ?? $default;
            }
        }

        return $default;
    }

    /** Current values for every field in an extension's schema (defaults merged with stored). */
    public static function settingValues(string $id): array
    {
        $out = [];
        foreach (self::settingsSchema($id) as $field) {
            $key = $field['key'];
            $out[$key] = self::setting($id, $key, $field['default'] ?? null);
        }

        return $out;
    }

    /** Resolve a requested asset surface to a real file path (for the asset route). */
    public static function assetPath(string $id, string $surface): ?string
    {
        $m = self::all()[$id] ?? null;
        // Core (first-party, always-loaded) widgets are served even though they
        // aren't in the marketplace enable list.
        if (! $m || (! self::isEnabled($id) && empty($m['core']))) {
            return null;
        }
        $file = $m['assets'][$surface] ?? null;
        if (! is_string($file)) {
            return null;
        }
        $path = realpath($m['_path'].'/'.$file);
        // Guard against path traversal: the resolved file must stay inside the package.
        if ($path && str_starts_with($path, realpath($m['_path']).DIRECTORY_SEPARATOR) && is_file($path)) {
            return $path;
        }

        return null;
    }
}
