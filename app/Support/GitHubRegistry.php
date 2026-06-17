<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Resolves a Convoro extension from a public GitHub repository — the registry
 * that lets extensions be "linked from GitHub" (Packagist-style) instead of
 * uploaded as zips. Reads the repo's extension.json + picks a download archive:
 *   - a PINNED release tag (frozen), or
 *   - the latest release tag (auto-refreshes on new releases), or
 *   - the default branch (no releases yet).
 */
class GitHubRegistry
{
    /** Normalize "https://github.com/owner/repo(.git)" or "owner/repo" → "owner/repo". */
    public static function normalizeRepo(string $input): ?string
    {
        $s = trim($input);
        $s = preg_replace('#^https?://github\.com/#i', '', $s);
        $s = preg_replace('#\.git$#', '', $s);
        $s = trim($s, '/ ');

        return preg_match('#^[\w.-]+/[\w.-]+$#', $s) ? $s : null;
    }

    private static function http(?string $token = null)
    {
        $req = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Convoro-Registry'])->timeout(15);
        $token = ($token !== null && $token !== '') ? $token : self::token();

        return $token !== '' ? $req->withToken($token) : $req;
    }

    /**
     * The token to use for a repo: the seller's connected token wins over the
     * global one. Pass the seller's token explicitly (e.g. from the product owner
     * or the submitting user) to read THEIR private repos.
     */
    public static function token(): string
    {
        return (string) (Settings::get('github.token') ?: config('services.github.token'));
    }

    /**
     * Fetch a repo file's contents through the REST API rather than
     * raw.githubusercontent.com, so the token unlocks PRIVATE repos too (raw.*
     * can't be authenticated). Returns the response, or null on miss.
     */
    private static function rawContents(string $repo, string $path, string $ref, ?string $token = null): ?\Illuminate\Http\Client\Response
    {
        $res = self::http($token)
            ->withHeaders(['Accept' => 'application/vnd.github.raw'])
            ->get("https://api.github.com/repos/{$repo}/contents/".ltrim($path, '/'), ['ref' => $ref]);

        return $res->successful() ? $res : null;
    }

    /**
     * @param  string|null  $pin  A specific release tag to resolve (frozen). NULL = latest release.
     * @param  string|null  $token  The seller's connected token (reads their private repos).
     * @return array{manifest:array,version:string,download_url:string,ref:string,default_branch:string,private:bool,icon_svg:?string}
     *
     * @throws \RuntimeException
     */
    public static function resolve(string $input, ?string $pin = null, ?string $token = null): array
    {
        $repo = self::normalizeRepo($input);
        if (! $repo) {
            throw new \RuntimeException('Enter a valid GitHub repository (owner/name).');
        }

        $info = self::http($token)->get("https://api.github.com/repos/{$repo}");
        if ($info->status() === 404) {
            throw new \RuntimeException("Repository {$repo} not found. Check the owner/name — and for a private repo, connect the GitHub account that owns it first.");
        }
        if (! $info->successful()) {
            throw new \RuntimeException('GitHub error: '.($info->json('message') ?? $info->status()));
        }
        $branch = $info->json('default_branch') ?: 'main';
        $private = (bool) $info->json('private', false);

        // The extension manifest must live at the repo root.
        $raw = self::rawContents($repo, 'extension.json', $branch, $token);
        if (! $raw) {
            throw new \RuntimeException('No extension.json found at the root of '.$repo.' ('.$branch.').');
        }
        $manifest = json_decode($raw->body(), true);
        if (! is_array($manifest) || empty($manifest['id']) || empty($manifest['name'])) {
            throw new \RuntimeException('extension.json is missing required fields (id, name).');
        }

        $pin = ($pin !== null && trim($pin) !== '') ? trim($pin) : null;

        if ($pin !== null) {
            // Pinned: resolve exactly this release tag and verify it exists.
            $check = self::http($token)->get("https://api.github.com/repos/{$repo}/releases/tags/{$pin}");
            if (! $check->successful()) {
                throw new \RuntimeException("Release tag “{$pin}” was not found on {$repo}.");
            }
            $ref = $pin;
            $download = "https://api.github.com/repos/{$repo}/zipball/{$pin}";
            $version = ltrim($pin, 'vV') ?: ($manifest['version'] ?? '0.0.0');
        } else {
            // Prefer the latest release tag; fall back to the default branch.
            $rel = self::http($token)->get("https://api.github.com/repos/{$repo}/releases/latest");
            if ($rel->successful() && $rel->json('tag_name')) {
                $tag = $rel->json('tag_name');
                $ref = $tag;
                $download = "https://api.github.com/repos/{$repo}/zipball/{$tag}";
                $version = ltrim($tag, 'vV') ?: ($manifest['version'] ?? '0.0.0');
            } else {
                $ref = $branch;
                $download = "https://api.github.com/repos/{$repo}/zipball/{$branch}";
                $version = (string) ($manifest['version'] ?? '0.0.0');
            }
        }

        return [
            'manifest' => $manifest,
            'version' => $version,
            'download_url' => $download,
            'ref' => $ref,
            'default_branch' => $branch,
            'private' => $private,
            'icon_svg' => self::fetchIcon($repo, $ref, $branch, $manifest, $token),
        ];
    }

    /**
     * Fetch the repo's icon as inline SVG markup so the directory/detail page can
     * show a real glyph even when the extension isn't installed locally. Tries the
     * resolved ref first, then the default branch (so an icon added after the last
     * release is still picked up). Best effort — null if none found.
     */
    private static function fetchIcon(string $repo, string $ref, string $branch, array $manifest, ?string $token = null): ?string
    {
        $path = $manifest['icon'] ?? 'icon.svg';
        if (! is_string($path) || trim($path) === '') {
            return null;
        }
        $path = ltrim(trim($path), '/');
        if (! str_ends_with(strtolower($path), '.svg')) {
            return null; // only inline SVG icons; raster icons go through `image`.
        }

        foreach (array_unique([$ref, $branch]) as $at) {
            try {
                $res = self::rawContents($repo, $path, $at, $token);
            } catch (\Throwable) {
                continue;
            }
            if ($res) {
                $svg = trim($res->body());
                if (str_contains($svg, '<svg')) {
                    return $svg;
                }
            }
        }

        return null;
    }

    private static function manifestType(array $m): string
    {
        $t = $m['type'] ?? 'extension';

        return in_array($t, ['extension', 'theme'], true) ? $t : 'extension';
    }

    /**
     * Buyers can't fetch a PRIVATE repo themselves, so pull the archive with the
     * seller's token and store it as the product's gated download. The buyer then
     * installs from the store (licenses.download / catalog.download), never the
     * repo. Sets $product->download_path (caller saves). Returns true on success.
     */
    public static function cacheArchive(Product $product, string $downloadUrl, ?string $token = null): bool
    {
        try {
            $res = self::http($token)->timeout(180)->get($downloadUrl);
        } catch (\Throwable) {
            return false;
        }
        if (! $res->successful() || ! str_starts_with($res->body(), 'PK')) {
            return false;
        }

        $rel = 'ext-cache/'.$product->slug.'-'.($product->ref ?: 'latest').'.zip';
        $abs = storage_path('app/'.$rel);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($abs));
        file_put_contents($abs, $res->body());
        $product->download_path = $rel;

        return true;
    }

    /** The seller's connected GitHub token for a product (null if not connected). */
    private static function ownerToken(Product $product): ?string
    {
        $token = $product->owner_id
            ? (string) (\App\Models\User::find($product->owner_id)?->github_token ?? '')
            : '';

        return $token !== '' ? $token : null;
    }

    /**
     * Link (create or re-link) a Product from a GitHub repo. Operator-initiated:
     * sets the pin explicitly (null clears it). Price/published are only set on
     * CREATE so a re-link never clobbers an existing listing's pricing.
     */
    public static function linkProduct(string $input, ?string $pin = null): Product
    {
        $repo = self::normalizeRepo($input);
        $pin = ($pin !== null && trim($pin) !== '') ? trim($pin) : null;
        $r = self::resolve($input, $pin);
        $m = $r['manifest'];

        $product = Product::firstOrNew(['package' => $m['id']]);
        $isNew = ! $product->exists;

        $product->fill([
            'slug' => Str::slug($m['id']),
            'name' => $m['name'],
            'type' => self::manifestType($m),
            'tagline' => Str::limit(strip_tags($m['description'] ?? ''), 180),
            'description' => $m['description'] ?? '',
            'version' => $r['version'],
            'source' => 'github',
            'repo' => $repo,
            'download_url' => $r['download_url'],
            'ref' => $r['ref'],
            'pinned_version' => $pin,
            'last_synced_at' => now(),
        ]);
        if (! empty($r['icon_svg'])) {
            $product->icon = $r['icon_svg'];
        }
        if ($isNew) {
            $product->price_cents = 0;
            $product->published = true;
            // Operator-linked (store owner) listings are trusted/first-party —
            // approve them directly rather than leaving them in submission review.
            $product->status = 'approved';
        }
        // Private repos can't be fetched by buyers — cache the archive store-side.
        if (! empty($r['private'])) {
            self::cacheArchive($product, $r['download_url']);
        }
        $product->save();

        try {
            CoverImage::generate($product);
        } catch (\Throwable) {
        }

        self::queueReview($product);

        return $product;
    }

    /**
     * Re-sync an existing GitHub product from its repo, honoring its pin.
     * Preserves price/published/featured/pin. Returns true if the resolved
     * version or download URL actually changed (i.e. a real update landed).
     */
    public static function syncProduct(Product $product): bool
    {
        if ($product->source !== 'github' || ! $product->repo) {
            return false;
        }

        $token = self::ownerToken($product);
        $r = self::resolve($product->repo, $product->pinned_version, $token);
        $m = $r['manifest'];

        $before = $product->version.'|'.$product->download_url;
        $product->fill([
            'name' => $m['name'],
            'type' => self::manifestType($m),
            'tagline' => Str::limit(strip_tags($m['description'] ?? ''), 180),
            'description' => $m['description'] ?? '',
            'version' => $r['version'],
            'download_url' => $r['download_url'],
            'ref' => $r['ref'],
            'last_synced_at' => now(),
        ]);
        if (! empty($r['icon_svg'])) {
            $product->icon = $r['icon_svg'];
        }
        $changed = $before !== ($r['version'].'|'.$r['download_url']);
        // Refresh the store-side cached archive for private repos on every change.
        if ($changed && ! empty($r['private'])) {
            self::cacheArchive($product, $r['download_url'], $token);
        }
        $product->save();

        if ($changed) {
            try {
                CoverImage::generate($product);
            } catch (\Throwable) {
            }
            self::queueReview($product);
        }

        return $changed;
    }

    /** Queue an AI security review for the product (if reviews are enabled). */
    public static function queueReview(Product $product): void
    {
        if (! ExtensionReview::enabled()) {
            return;
        }
        $product->forceFill(['review_status' => 'queued'])->save();
        \App\Jobs\ReviewExtensionJob::dispatch($product->id);
    }
}
