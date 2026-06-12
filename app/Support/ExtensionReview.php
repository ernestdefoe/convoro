<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Facades\Http;

/**
 * Unbiased AI security review of a GitHub-linked extension. Downloads the exact
 * archive the listing would install, bundles the source (size-capped for
 * shared-host memory), and asks an LLM to audit it for risky/malicious patterns.
 * Results are stored on the Product and shown on the directory — a strong signal
 * (not a guarantee) of whether something is safe to install.
 */
class ExtensionReview
{
    /** Source files worth auditing. */
    private const EXTS = ['php', 'js', 'ts', 'vue', 'mjs', 'cjs', 'json', 'blade.php', 'css', 'html', 'sh'];

    /** Never audit (noise / vendored / binary). */
    private const SKIP_DIRS = ['node_modules', 'vendor', 'dist', 'build', '.git', 'tests', 'test', 'fonts', 'images', 'img'];

    private const MAX_FILE = 40_000;   // bytes per file

    private const MAX_TOTAL = 180_000; // bytes sent to the model

    public static function enabled(): bool
    {
        return (bool) Settings::get('review.enabled', true) && Llm::configured();
    }

    /** True if there's source we can audit: a GitHub repo, an uploaded zip, OR a
     *  locally-installed package (e.g. a first-party extension bundled in packages/). */
    public static function reviewable(Product $product): bool
    {
        return ($product->source === 'github' && $product->download_url)
            || (bool) $product->download_path
            || self::localPath($product) !== null;
    }

    /** Path to the installed package dir matching this product's slug/package, or null. */
    private static function localPath(Product $product): ?string
    {
        $id = (string) ($product->slug ?: $product->package);
        if ($id === '') {
            return null;
        }
        $manifest = ExtensionManager::all()[$id] ?? null;
        $path = $manifest['_path'] ?? null;

        return (is_string($path) && is_dir($path)) ? $path : null;
    }

    /** Run the review for a product. Persists the verdict; returns the rating. */
    public static function review(Product $product): string
    {
        if (! self::reviewable($product)) {
            return 'error';
        }

        $product->forceFill(['review_status' => 'reviewing'])->save();

        try {
            $bundle = self::fetchSource($product);
            if ($bundle === '') {
                throw new \RuntimeException('Could not read any source from the repository archive.');
            }

            $verdict = self::audit($product, $bundle);

            $product->forceFill([
                'review_rating' => $verdict['rating'],
                'review_score' => $verdict['score'],
                'review_summary' => $verdict['summary'],
                'review_findings' => $verdict['findings'],
                'review_model' => Llm::model(),
                'reviewed_version' => $product->version,
                'review_status' => 'done',
                'reviewed_at' => now(),
            ])->save();

            return $verdict['rating'];
        } catch (\Throwable $e) {
            $product->forceFill([
                'review_status' => 'error',
                'review_summary' => 'Review failed: '.$e->getMessage(),
                'reviewed_at' => now(),
            ])->save();

            return 'error';
        }
    }

    /** Locate the source (GitHub zip, uploaded zip, or local installed package). */
    private static function fetchSource(Product $product): string
    {
        // No remote/uploaded archive but the extension is installed locally
        // (e.g. a bundled first-party extension): audit the files on disk.
        if (! ($product->source === 'github' && $product->download_url) && ! $product->download_path) {
            $dir = self::localPath($product);
            if ($dir !== null) {
                return self::bundleDir($dir);
            }
        }

        $tmpZip = null;
        if ($product->source === 'github' && $product->download_url) {
            $tmpZip = tempnam(sys_get_temp_dir(), 'cvr_zip_');
            $res = Http::timeout(60)->get($product->download_url);
            if (! $res->successful()) {
                @unlink($tmpZip);
                throw new \RuntimeException('Could not download the repository archive.');
            }
            file_put_contents($tmpZip, $res->body());
            $zipPath = $tmpZip;
        } elseif ($product->download_path) {
            $zipPath = storage_path('app/'.ltrim($product->download_path, '/'));
            if (! is_file($zipPath)) {
                throw new \RuntimeException('The uploaded package file is missing.');
            }
        } else {
            throw new \RuntimeException('No source archive available to review.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            if ($tmpZip) {
                @unlink($tmpZip);
            }
            throw new \RuntimeException('The archive could not be opened.');
        }

        $bundle = '';
        $total = 0;
        for ($i = 0; $i < $zip->numFiles && $total < self::MAX_TOTAL; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }
            $lower = strtolower($name);
            // Strip the GitHub top-level dir for matching.
            $rel = preg_replace('#^[^/]+/#', '', $name);
            if (self::skip($lower)) {
                continue;
            }
            if (! self::wanted($lower)) {
                continue;
            }
            $stat = $zip->statIndex($i);
            if (($stat['size'] ?? 0) > self::MAX_FILE * 4) {
                continue; // skip very large files outright
            }
            $code = $zip->getFromIndex($i);
            if ($code === false || $code === '') {
                continue;
            }
            $code = substr($code, 0, self::MAX_FILE);
            $chunk = "\n\n===== FILE: {$rel} =====\n".$code;
            $total += strlen($chunk);
            $bundle .= $chunk;
        }
        $zip->close();
        if ($tmpZip) {
            @unlink($tmpZip); // only the temp download; never the uploaded file
        }

        return $bundle;
    }

    /** Bundle audited source files from a local directory (same caps as the zip path). */
    private static function bundleDir(string $dir): string
    {
        $bundle = '';
        $total = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($total >= self::MAX_TOTAL) {
                break;
            }
            if (! $file->isFile()) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($dir))), '/');
            $lower = strtolower($rel);
            if (self::skip('/'.$lower) || ! self::wanted($lower)) {
                continue;
            }
            if ($file->getSize() > self::MAX_FILE * 4) {
                continue;
            }
            $code = @file_get_contents($file->getPathname());
            if ($code === false || $code === '') {
                continue;
            }
            $code = substr($code, 0, self::MAX_FILE);
            $chunk = "\n\n===== FILE: {$rel} =====\n".$code;
            $total += strlen($chunk);
            $bundle .= $chunk;
        }

        return $bundle;
    }

    private static function skip(string $lower): bool
    {
        foreach (self::SKIP_DIRS as $d) {
            if (str_contains($lower, "/{$d}/")) {
                return true;
            }
        }

        return str_contains($lower, '.min.') || str_ends_with($lower, 'lock')
            || str_ends_with($lower, 'package-lock.json') || str_ends_with($lower, 'composer.lock');
    }

    private static function wanted(string $lower): bool
    {
        foreach (self::EXTS as $ext) {
            if (str_ends_with($lower, '.'.$ext)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{rating:string,score:int,summary:string,findings:array} */
    private static function audit(Product $product, string $bundle): array
    {
        $system = <<<'SYS'
You are an impartial application-security auditor reviewing a third-party Convoro
forum extension (PHP + JS/Vue) before it is offered for install. Judge ONLY the
provided source. Look for: remote code execution, arbitrary file read/write,
unsanitized SQL, command/shell execution, SSRF, hardcoded credentials or exfil
of data/keys to external hosts, obfuscated/minified payloads, eval()/dynamic
includes, dangerous deserialization, missing authorization on admin actions,
and anything that phones home or behaves maliciously. Normal framework use is
fine — do not invent issues. Be conservative and factual.

Respond with ONLY a JSON object, no prose, no markdown fences:
{"rating":"safe|caution|unsafe","score":0-100,"summary":"one or two sentences","findings":[{"severity":"low|medium|high|critical","category":"short label","title":"short","detail":"what + where"}]}
"safe" = no meaningful risk; "caution" = minor/uncertain issues; "unsafe" = real
security risk a user should not install. score 100 = perfectly clean.
SYS;

        $user = "Extension: {$product->name} (package {$product->package}, repo {$product->repo}, v{$product->version}).\n"
            ."Audit the following source files:\n".$bundle;

        $raw = Llm::chat($system, $user, 1800, 'review');
        $json = self::extractJson($raw);

        $rating = in_array($json['rating'] ?? '', ['safe', 'caution', 'unsafe'], true) ? $json['rating'] : 'caution';
        $score = (int) max(0, min(100, (int) ($json['score'] ?? 0)));
        $findings = [];
        foreach ((array) ($json['findings'] ?? []) as $f) {
            if (! is_array($f)) {
                continue;
            }
            $findings[] = [
                'severity' => in_array($f['severity'] ?? '', ['low', 'medium', 'high', 'critical'], true) ? $f['severity'] : 'low',
                'category' => substr((string) ($f['category'] ?? 'general'), 0, 60),
                'title' => substr((string) ($f['title'] ?? ''), 0, 160),
                'detail' => substr((string) ($f['detail'] ?? ''), 0, 600),
            ];
        }

        return [
            'rating' => $rating,
            'score' => $score,
            'summary' => substr((string) ($json['summary'] ?? ''), 0, 600),
            'findings' => $findings,
        ];
    }

    private static function extractJson(string $raw): array
    {
        $s = trim($raw);
        $s = preg_replace('/^```(?:json)?|```$/m', '', $s);
        $start = strpos($s, '{');
        $end = strrpos($s, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new \RuntimeException('The model did not return a parseable verdict.');
        }
        $data = json_decode(substr($s, $start, $end - $start + 1), true);
        if (! is_array($data)) {
            throw new \RuntimeException('The model returned invalid JSON.');
        }

        return $data;
    }
}
