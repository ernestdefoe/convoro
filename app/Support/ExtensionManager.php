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

        return self::$cache = $found;
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
            'namespace' => isset($raw['namespace']) ? rtrim((string) $raw['namespace'], '\\').'\\' : null,
            'provider' => $raw['provider'] ?? null,            // FQCN of a ServiceProvider
            'migrations' => $raw['migrations'] ?? null,        // dir relative to package
            'permissions' => is_array($raw['permissions'] ?? null) ? $raw['permissions'] : [],
            'assets' => is_array($raw['assets'] ?? null) ? $raw['assets'] : [],
            'premium' => (bool) ($raw['premium'] ?? false),
            'price' => $raw['price'] ?? 0,
            'icon' => $raw['icon'] ?? null,
            '_path' => $dir,
            '_writable' => is_writable($dir),
        ];
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
        foreach (self::enabled() as $m) {
            $file = $m['assets'][$surface] ?? null;
            if (is_string($file) && is_file($m['_path'].'/'.$file)) {
                $out[] = ['id' => $m['id'], 'url' => "/ext-asset/{$m['id']}/{$surface}"];
            }
        }

        return $out;
    }

    /** Resolve a requested asset surface to a real file path (for the asset route). */
    public static function assetPath(string $id, string $surface): ?string
    {
        $m = self::all()[$id] ?? null;
        if (! $m || ! self::isEnabled($id)) {
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
