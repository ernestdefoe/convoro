<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Resolves a Convoro extension from a public GitHub repository — the registry
 * that lets extensions be "linked from GitHub" (Packagist-style) instead of
 * uploaded as zips. Reads the repo's extension.json + picks a download archive
 * (latest release tag, else the default branch).
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
     * @return array{manifest:array,version:string,download_url:string,ref:string,default_branch:string}
     *
     * @throws \RuntimeException
     */
    public static function resolve(string $input): array
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

        return [
            'manifest' => $manifest,
            'version' => $version,
            'download_url' => $download,
            'ref' => $ref,
            'default_branch' => $branch,
        ];
    }

    /** Build/refresh a Product row from a GitHub repo. Returns the Product. */
    public static function linkProduct(string $input): \App\Models\Product
    {
        $r = self::resolve($input);
        $m = $r['manifest'];
        $repo = self::normalizeRepo($input);

        return \App\Models\Product::updateOrCreate(
            ['package' => $m['id']],
            [
                'slug' => Str::slug($m['id']),
                'name' => $m['name'],
                'type' => in_array(($m['type'] ?? 'extension'), ['extension', 'theme'], true) ? ($m['type'] ?? 'extension') : 'extension',
                'tagline' => Str::limit(strip_tags($m['description'] ?? ''), 180),
                'description' => $m['description'] ?? '',
                'version' => $r['version'],
                'source' => 'github',
                'repo' => $repo,
                'download_url' => $r['download_url'],
                'ref' => $r['ref'],
                'price_cents' => 0,
                'published' => true,
            ],
        );
    }
}
