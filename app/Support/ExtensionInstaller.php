<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Installs / enables / disables / removes Convoro extensions — all without
 * Composer or a shell, so it works through the admin Marketplace on shared
 * hosting. Uploaded packages land in storage/app/extensions/{id}/.
 */
class ExtensionInstaller
{
    public static function targetRoot(): string
    {
        return storage_path('app/extensions');
    }

    /**
     * Install from an uploaded .zip. The zip must contain a single extension
     * (an extension.json at its root, or nested one level). Returns the id.
     *
     * @throws \RuntimeException on any validation failure
     */
    public static function installFromZip(string $zipPath): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required to install extensions.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Could not open the uploaded archive.');
        }

        // Extract to a temp dir first so we can read + validate the manifest
        // before committing anything.
        $tmp = storage_path('app/ext-tmp/'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($tmp);

        try {
            self::safeExtract($zip, $tmp);
            $zip->close();

            $pkgDir = self::locateManifestDir($tmp);
            if ($pkgDir === null) {
                throw new \RuntimeException('No extension.json found in the archive.');
            }

            $manifest = ExtensionManager::manifest($pkgDir.'/extension.json', $pkgDir);
            if ($manifest === null) {
                throw new \RuntimeException('The extension.json is missing required fields (id, name).');
            }

            self::assertCompatible($manifest);

            $id = $manifest['id'];
            if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/i', $id)) {
                throw new \RuntimeException('Invalid extension id.');
            }

            // Move into place (replace any prior install of the same id).
            File::ensureDirectoryExists(self::targetRoot());
            $dest = self::targetRoot().'/'.$id;
            if (is_dir($dest)) {
                File::deleteDirectory($dest);
            }
            File::moveDirectory($pkgDir, $dest);

            ExtensionManager::flushCache();

            return $id;
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    /** Download a release zip from a URL and install it. Returns the extension id. */
    public static function installFromUrl(string $url): string
    {
        $tmp = storage_path('app/ext-tmp/'.bin2hex(random_bytes(8)).'.zip');
        File::ensureDirectoryExists(dirname($tmp));

        $res = \Illuminate\Support\Facades\Http::timeout(120)->get($url);
        if (! $res->successful()) {
            throw new \RuntimeException('Could not download the package (HTTP '.$res->status().').');
        }
        file_put_contents($tmp, $res->body());

        try {
            return self::installFromZip($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** Enable an installed extension and run its migrations. */
    public static function enable(string $id): void
    {
        $m = ExtensionManager::all()[$id] ?? null;
        if (! $m) {
            throw new \RuntimeException('Extension not found.');
        }
        self::assertCompatible($m);

        self::migrate($m);

        ExtensionManager::setEnabled($id, true);
        self::recache();
    }

    public static function disable(string $id): void
    {
        ExtensionManager::setEnabled($id, false);
        self::recache();
    }

    /**
     * Remove an installed extension. Rolls back its migrations first when
     * possible, then deletes the package (only from the writable storage root;
     * bundled first-party extensions can't be deleted this way).
     */
    public static function uninstall(string $id): void
    {
        $m = ExtensionManager::all()[$id] ?? null;
        if (! $m) {
            return;
        }

        if ($m['migrations'] && is_dir($m['_path'].'/'.$m['migrations'])) {
            try {
                Artisan::call('migrate:rollback', [
                    '--path' => $m['_path'].'/'.$m['migrations'],
                    '--realpath' => true,
                    '--force' => true,
                ]);
            } catch (\Throwable) {
                // Best-effort: a missing-down migration shouldn't block removal.
            }
        }

        ExtensionManager::setEnabled($id, false);

        $storageDir = self::targetRoot().'/'.$id;
        if (is_dir($storageDir)) {
            File::deleteDirectory($storageDir);
        }

        ExtensionManager::flushCache();
        self::recache();
    }

    private static function migrate(array $m): void
    {
        if ($m['migrations'] && is_dir($m['_path'].'/'.$m['migrations'])) {
            Artisan::call('migrate', [
                '--path' => $m['_path'].'/'.$m['migrations'],
                '--realpath' => true,
                '--force' => true,
            ]);
        }
    }

    /** Rebuild caches so newly (de)activated providers/routes take effect. */
    private static function recache(): void
    {
        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
            // ignore in environments where caching isn't configured
        }
    }

    /** Throw unless the extension's `convoro` constraint matches the running version. */
    private static function assertCompatible(array $m): void
    {
        $constraint = (string) ($m['convoro'] ?? '*');
        // Compare against the release core, ignoring any -dev/-rc/+build suffix
        // so dev builds (e.g. 0.1.0-dev) still satisfy ">=0.1.0".
        $version = preg_replace('/[-+].*$/', '', (string) config('convoro.version', '0.0.0'));
        if (! self::satisfies($version, $constraint)) {
            throw new \RuntimeException("“{$m['name']}” requires Convoro {$constraint}; you are on {$version}.");
        }
    }

    /**
     * Tiny semver check — enough for our constraints without pulling in
     * composer/semver. Supports `*`, exact `x.y.z`, and comparator forms
     * `>=`, `>`, `<=`, `<`, `^x.y.z` (caret = same major), and `~x.y.z`.
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (preg_split('/\s*,\s*/', $constraint) as $part) {
            if (! self::satisfiesOne($version, $part)) {
                return false;
            }
        }

        return true;
    }

    private static function satisfiesOne(string $version, string $part): bool
    {
        if (preg_match('/^(>=|<=|>|<|=)?\s*(.+)$/', $part, $mt)) {
            $op = $mt[1] ?: '=';
            $target = ltrim($mt[2], 'v');

            if (str_starts_with($part, '^')) {
                $target = ltrim(substr($part, 1), 'v');
                $maj = explode('.', $target)[0];
                return version_compare($version, $target, '>=')
                    && version_compare($version, ((int) $maj + 1).'.0.0', '<');
            }
            if (str_starts_with($part, '~')) {
                $target = ltrim(substr($part, 1), 'v');
                $seg = explode('.', $target);
                $upper = isset($seg[1]) ? $seg[0].'.'.((int) $seg[1] + 1).'.0' : ((int) $seg[0] + 1).'.0.0';
                return version_compare($version, $target, '>=')
                    && version_compare($version, $upper, '<');
            }

            return version_compare($version, $target, $op);
        }

        return version_compare($version, ltrim($part, 'v'), '=');
    }

    /** Extract a zip while refusing absolute paths and `..` traversal. */
    private static function safeExtract(ZipArchive $zip, string $dest): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $norm = str_replace('\\', '/', $name);
            if (str_starts_with($norm, '/') || str_contains($norm, '../') || str_contains($norm, '..\\')) {
                throw new \RuntimeException('Archive contains an unsafe path: '.$name);
            }
        }
        if (! $zip->extractTo($dest)) {
            throw new \RuntimeException('Failed to extract the archive.');
        }
    }

    /** Find the directory that directly contains extension.json (root or one level deep). */
    private static function locateManifestDir(string $base): ?string
    {
        if (is_file($base.'/extension.json')) {
            return $base;
        }
        foreach (File::directories($base) as $dir) {
            if (is_file($dir.'/extension.json')) {
                return $dir;
            }
        }

        return null;
    }
}
