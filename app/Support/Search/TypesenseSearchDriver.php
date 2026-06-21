<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional upgrade driver that talks to a hosted Typesense instance over HTTP.
 *
 * Typesense is a daemon and CANNOT run on typical shared hosting — but Convoro
 * doesn't need to host it: point this at Typesense Cloud (or a Typesense on a
 * small VPS) and the search requests go out over HTTPS from any host. When the
 * engine is unconfigured or unreachable the SearchManager silently falls back
 * to the database (ngram) driver, so enabling this is never a hard dependency.
 *
 * Sync: index/delete are called from the same post-change path that feeds the
 * Ask index, so the collection tracks topics as they're created/edited/removed.
 */
class TypesenseSearchDriver implements SearchDriver
{
    public function __construct(
        private string $url,
        private string $apiKey,
        private string $collection,
        private int $timeout = 3,
    ) {
        $this->url = rtrim($url, '/');
    }

    public static function fromConfig(): self
    {
        $c = (array) config('convoro.search.typesense', []);

        return new self(
            (string) ($c['url'] ?? ''),
            (string) ($c['api_key'] ?? ''),
            (string) ($c['collection'] ?? 'convoro_topics'),
            (int) ($c['timeout'] ?? 3),
        );
    }

    /**
     * Build from the convoro-typesense extension's settings (what the operator
     * fills in on the extension's admin page), falling back to env/config for
     * any field they leave blank so headless setups still work.
     */
    public static function fromExtension(): self
    {
        $get = fn (string $k, $d = '') => \App\Support\ExtensionManager::setting('convoro-typesense', $k, $d);
        $c = (array) config('convoro.search.typesense', []);

        return new self(
            (string) ($get('url') ?: ($c['url'] ?? '')),
            (string) ($get('api_key') ?: ($c['api_key'] ?? '')),
            (string) ($get('collection', 'convoro_topics') ?: ($c['collection'] ?? 'convoro_topics')),
            (int) ($c['timeout'] ?? 3),
        );
    }

    public function available(): bool
    {
        if ($this->url === '' || $this->apiKey === '') {
            return false;
        }

        // Health check is cached so we don't pay a round-trip on every search;
        // a brief outage degrades to the database driver for at most a minute.
        return Cache::remember('convoro.search.typesense.up', 60, function () {
            try {
                return $this->client()->get($this->url . '/health')->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function topicIds(string $query, int $limit = 30): array
    {
        try {
            $res = $this->client()->get($this->url . '/collections/' . rawurlencode($this->collection) . '/documents/search', [
                'q' => $query,
                'query_by' => 'title,body',
                'per_page' => max(1, min(250, $limit)),
                'include_fields' => 'id',
            ]);

            if (! $res->successful()) {
                return [];
            }

            $ids = [];
            foreach ((array) $res->json('hits', []) as $hit) {
                $id = (int) ($hit['document']['id'] ?? 0);
                if ($id > 0 && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            Log::warning('[convoro] Typesense search failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Upsert one topic document. Safe to call when Typesense isn't configured
     * (no-op) so callers don't have to guard every write.
     */
    public function indexTopic(int $topicId, string $title, string $body): void
    {
        if ($this->url === '' || $this->apiKey === '') {
            return;
        }
        try {
            $this->client()->post(
                $this->url . '/collections/' . rawurlencode($this->collection) . '/documents?action=upsert',
                ['id' => (string) $topicId, 'title' => $title, 'body' => $body, 'topic_id' => $topicId]
            );
        } catch (\Throwable $e) {
            Log::warning('[convoro] Typesense index failed: ' . $e->getMessage());
        }
    }

    public function deleteTopic(int $topicId): void
    {
        if ($this->url === '' || $this->apiKey === '') {
            return;
        }
        try {
            $this->client()->delete(
                $this->url . '/collections/' . rawurlencode($this->collection) . '/documents/' . $topicId
            );
        } catch (\Throwable) {
            // A missing document on delete is not an error worth surfacing.
        }
    }

    /**
     * Create the collection if it doesn't exist yet. Idempotent — a 409 from
     * an already-present collection is ignored.
     */
    public function ensureCollection(): void
    {
        if ($this->url === '' || $this->apiKey === '') {
            return;
        }
        try {
            $this->client()->post($this->url . '/collections', [
                'name' => $this->collection,
                'fields' => [
                    ['name' => 'title', 'type' => 'string'],
                    ['name' => 'body', 'type' => 'string'],
                    ['name' => 'topic_id', 'type' => 'int64'],
                ],
            ]);
        } catch (\Throwable) {
            // Already exists / transient — ensureCollection is best-effort.
        }
    }

    private function client()
    {
        return Http::withHeaders(['X-TYPESENSE-API-KEY' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
