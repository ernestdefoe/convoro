<?php

namespace App\Support;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ActivityPub (Phase 1) — outbound federation. Makes the community a single
 * discoverable, followable actor: fediverse users (Mastodon, Lemmy, …) can
 * follow @{username}@{host} and receive each new topic as a post.
 *
 * Implements WebFinger discovery, the actor/outbox documents, an inbox that
 * verifies HTTP Signatures and handles Follow/Undo, and signed delivery of new
 * topics to followers. Off by default (admin enables it).
 */
class Federation
{
    public const CTYPE = 'application/activity+json';

    public static function enabled(): bool
    {
        return (bool) Settings::get('federation.enabled', false);
    }

    public static function host(): string
    {
        return (string) (config('convoro.community_domain') ?: parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    public static function base(): string
    {
        return 'https://'.self::host();
    }

    public static function username(): string
    {
        $u = (string) Settings::get('federation.username', '');

        return $u !== '' ? $u : (Str::slug((string) (Settings::get('site.name') ?: 'community')) ?: 'community');
    }

    public static function actorUrl(): string
    {
        return self::base().'/federation/actor';
    }

    public static function handle(): string
    {
        return '@'.self::username().'@'.self::host();
    }

    // ---- Keypair (stored in Settings; generated on first use) ----

    /** @return array{public:string,private:string} PEM strings */
    public static function keys(): array
    {
        $pub = (string) Settings::get('federation.public_key', '');
        $priv = (string) Settings::get('federation.private_key', '');
        if ($pub !== '' && $priv !== '') {
            return ['public' => $pub, 'private' => $priv];
        }

        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($res === false) {
            throw new \RuntimeException('Could not generate a federation keypair (OpenSSL).');
        }
        openssl_pkey_export($res, $priv);
        $pub = (string) openssl_pkey_get_details($res)['key'];
        Settings::setMany(['federation.public_key' => $pub, 'federation.private_key' => $priv]);

        return ['public' => $pub, 'private' => $priv];
    }

    public static function publicKeyPem(): string
    {
        return self::keys()['public'];
    }

    // ---- Documents ----

    /** The ActivityPub actor document for the community. */
    public static function actor(): array
    {
        $base = self::base();
        $url = self::actorUrl();
        $icon = $base.'/icons/icon-192.png?v='.Settings::get('icons.rev', '1');

        return [
            '@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
            'id' => $url,
            'type' => 'Service',
            'preferredUsername' => self::username(),
            'name' => (string) (Settings::get('site.name') ?: 'Community'),
            'summary' => (string) (Settings::get('site.tagline') ?: ''),
            'manuallyApprovesFollowers' => false,
            'discoverable' => true,
            'inbox' => $base.'/federation/inbox',
            'outbox' => $base.'/federation/outbox',
            'followers' => $base.'/federation/followers',
            'url' => $base.'/',
            'icon' => ['type' => 'Image', 'mediaType' => 'image/png', 'url' => $icon],
            'publicKey' => [
                'id' => $url.'#main-key',
                'owner' => $url,
                'publicKeyPem' => self::publicKeyPem(),
            ],
        ];
    }

    public static function webfinger(): array
    {
        return [
            'subject' => 'acct:'.self::username().'@'.self::host(),
            'aliases' => [self::actorUrl()],
            'links' => [
                ['rel' => 'self', 'type' => self::CTYPE, 'href' => self::actorUrl()],
                ['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => self::base().'/'],
            ],
        ];
    }

    /** A Create→Note activity announcing a topic (Mastodon renders the Note). */
    public static function createActivityForTopic(Topic $topic): array
    {
        $base = self::base();
        $topicUrl = $base.'/t/'.$topic->slug;
        $actor = self::actorUrl();
        $published = ($topic->created_at ?? now())->toAtomString();
        $excerpt = trim(Str::limit(strip_tags((string) optional($topic->firstPost)->body_html), 280));
        $content = '<p><strong>'.e($topic->title).'</strong></p>'
            .($excerpt !== '' ? '<p>'.e($excerpt).'</p>' : '')
            .'<p><a href="'.e($topicUrl).'">'.e($topicUrl).'</a></p>';

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $topicUrl.'#create',
            'type' => 'Create',
            'actor' => $actor,
            'published' => $published,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$base.'/federation/followers'],
            'object' => [
                'id' => $topicUrl,
                'type' => 'Note',
                'attributedTo' => $actor,
                'content' => $content,
                'url' => $topicUrl,
                'published' => $published,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'cc' => [$base.'/federation/followers'],
            ],
        ];
    }

    /** Queue delivery of a new topic to all followers (no-op if disabled / no followers). */
    public static function announceTopic(Topic $topic): void
    {
        try {
            if (! self::enabled() || ! \Illuminate\Support\Facades\Schema::hasTable('federation_followers')) {
                return;
            }
            if (DB::table('federation_followers')->doesntExist()) {
                return;
            }
            \App\Jobs\DeliverActivity::dispatch(self::createActivityForTopic($topic))->afterCommit();
        } catch (\Throwable $e) {
            Log::debug('Federation announce skipped: '.$e->getMessage());
        }
    }

    // ---- HTTP Signatures (draft-cavage, as Mastodon uses) ----

    /** Sign an outgoing request; returns headers to attach. */
    public static function signHeaders(string $method, string $url, ?string $body = null): array
    {
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $method = strtolower($method);

        $headers = [
            'Host' => $host,
            'Date' => $date,
        ];
        $signedHeaders = ['(request-target)', 'host', 'date'];
        $lines = [
            '(request-target): '.$method.' '.$path,
            'host: '.$host,
            'date: '.$date,
        ];

        if ($body !== null) {
            $digest = 'SHA-256='.base64_encode(hash('sha256', $body, true));
            $headers['Digest'] = $digest;
            $headers['Content-Type'] = self::CTYPE;
            $signedHeaders[] = 'digest';
            $signedHeaders[] = 'content-type';
            $lines[] = 'digest: '.$digest;
            $lines[] = 'content-type: '.self::CTYPE;
        }

        openssl_sign(implode("\n", $lines), $sig, self::keys()['private'], OPENSSL_ALGO_SHA256);
        $headers['Signature'] = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            self::actorUrl().'#main-key',
            implode(' ', $signedHeaders),
            base64_encode((string) $sig),
        );

        return $headers;
    }

    /** Verify an incoming signed request against its actor's public key. */
    public static function verifyRequest(Request $request): bool
    {
        $sigHeader = (string) $request->header('Signature', '');
        if ($sigHeader === '') {
            return false;
        }
        $params = self::parseSignature($sigHeader);
        if (empty($params['keyId']) || empty($params['headers']) || empty($params['signature'])) {
            return false;
        }

        $actor = self::fetchActor($params['keyId']);
        $pem = $actor['publicKey']['publicKeyPem'] ?? null;
        if (! $pem) {
            return false;
        }

        $lines = [];
        foreach (explode(' ', $params['headers']) as $h) {
            if ($h === '(request-target)') {
                $lines[] = '(request-target): '.strtolower($request->method()).' '.$request->getRequestUri();
            } elseif ($h === 'digest') {
                $lines[] = 'digest: '.$request->header('Digest');
            } else {
                $lines[] = $h.': '.$request->header($h);
            }
        }

        // If a digest was signed, confirm it matches the body.
        if (str_contains($params['headers'], 'digest')) {
            $expected = 'SHA-256='.base64_encode(hash('sha256', $request->getContent(), true));
            if (! hash_equals($expected, (string) $request->header('Digest'))) {
                return false;
            }
        }

        return openssl_verify(implode("\n", $lines), base64_decode($params['signature']), $pem, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @return array<string,string> */
    private static function parseSignature(string $header): array
    {
        $out = [];
        preg_match_all('/(\w+)="([^"]*)"/', $header, $m, PREG_SET_ORDER);
        foreach ($m as $pair) {
            $out[$pair[1]] = $pair[2];
        }

        return $out;
    }

    /** Fetch (and cache) a remote actor document. Accepts an actor or key URL. */
    public static function fetchActor(string $url): ?array
    {
        $url = strtok($url, '#') ?: $url; // strip #main-key fragment

        return Cache::remember('fed:actor:'.sha1($url), 3600, function () use ($url) {
            try {
                $headers = self::signHeaders('get', $url);
                $headers['Accept'] = self::CTYPE;
                $res = Http::withHeaders($headers)->timeout(8)->get($url);

                return $res->ok() ? $res->json() : null;
            } catch (\Throwable $e) {
                Log::debug('fetchActor failed: '.$e->getMessage());

                return null;
            }
        });
    }
}
