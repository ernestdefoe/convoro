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

    private static function http()
    {
        $req = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Convoro-Registry'])->timeout(15);
        $token = (string) (Settings::get('github.token') ?: config('services.github.token'));

        return $token !== '' ? $req->withToken($token) : $req;
    }

    /**
     * @param  string|null  $pin  A specific release tag to resolve (frozen). NULL = latest release.
     * @return array{manifest:array,version:string,download_url:string,ref:string,default_branch:string}
     *
     * @throws \RuntimeException
     */
    public static function resolve(string $input, ?string $pin = null): array
    {
        $repo = self::normalizeRepo($input);
        if (! $repo) {
            throw new \RuntimeException('Enter a valid GitHub repository (owner/name).');
        }

        $info = self::http()->get("https://api.github.com/repos/{$repo}");
        if ($info->status() === 404) {
            throw new \RuntimeException("Repository {$repo} not found (it must be public).");
        }
        if (! $info->successful()) {
            throw new \RuntimeException('GitHub error: '.($info->json('message') ?? $info->status()));
        }
        $branch = $info->json('default_branch') ?: 'main';

        // The extension manifest must live at the repo root.
        $raw = Http::timeout(15)->get("https://raw.githubusercontent.com/{$repo}/{$branch}/extension.json");
        if (! $raw->successful()) {
            throw new \RuntimeException('No extension.json found at the root of '.$repo.' ('.$branch.').');
        }
        $manifest = json_decode($raw->body(), true);
        if (! is_array($manifest) || empty($manifest['id']) || empty($manifest['name'])) {
            throw new \RuntimeException('extension.json is missing required fields (id, name).');
        }

        $pin = ($pin !== null && trim($pin) !== '') ? trim($pin) : null;

        if ($pin !== null) {
            // Pinned: resolve exactly this release tag and verify it exists.
            $check = self::http()->get("https://api.github.com/repos/{$repo}/releases/tags/{$pin}");
            if (! $check->successful()) {
                throw new \RuntimeException("Release tag “{$pin}” was not found on {$repo}.");
            }
            $ref = $pin;
            $download = "https://github.com/{$repo}/archive/refs/tags/{$pin}.zip";
            $version = ltrim($pin, 'vV') ?: ($manifest['version'] ?? '0.0.0');
        } else {
            // Prefer the latest release tag; fall back to the default branch.
            $rel = self::http()->get("https://api.github.com/repos/{$repo}/releases/latest");
            if ($rel->successful() && $rel->json('tag_name')) {
                $tag = $rel->json('tag_name');
                $ref = $tag;
                $download = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";
                $version = ltrim($tag, 'vV') ?: ($manifest['version'] ?? '0.0.0');
            } else {
                $ref = $branch;
                $download = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
                $version = (string) ($manifest['version'] ?? '0.0.0');
            }
        }

        return [
            'manifest' => $manifest,
            'version' => $version,
            'download_url' => $download,
            'ref' => $ref,
            'default_branch' => $branch,
        ];
    }

    private static function manifestType(array $m): string
    {
        $t = $m['type'] ?? 'extension';

        return in_array($t, ['extension', 'theme'], true) ? $t : 'extension';
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
        if ($isNew) {
            $product->price_cents = 0;
            $product->published = true;
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

        $r = self::resolve($product->repo, $product->pinned_version);
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
        $changed = $before !== ($r['version'].'|'.$r['download_url']);
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
