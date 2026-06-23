<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
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

    // ---- Per-user actors (Phase 3) ----

    /** A member's unique fediverse username (lazy: slug of name, deduped by id). */
    public static function userUsername(User $user): string
    {
        if ($user->ap_username) {
            return $user->ap_username;
        }
        $base = Str::slug(Username::display($user->name, (int) $user->id), '_') ?: 'member';
        $candidate = $base;
        // Avoid clashing with the community handle or another member.
        if ($candidate === self::username() || User::where('ap_username', $candidate)->exists()) {
            $candidate = $base.$user->id;
        }
        $user->forceFill(['ap_username' => $candidate])->save();

        return $candidate;
    }

    /** @return array{public:string,private:string} a member's own keypair (lazy) */
    public static function userKeys(User $user): array
    {
        if ($user->ap_public_key && $user->ap_private_key) {
            return ['public' => $user->ap_public_key, 'private' => $user->ap_private_key];
        }
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($res === false) {
            throw new \RuntimeException('Could not generate a user federation keypair (OpenSSL).');
        }
        openssl_pkey_export($res, $priv);
        $pub = (string) openssl_pkey_get_details($res)['key'];
        $user->forceFill(['ap_public_key' => $pub, 'ap_private_key' => $priv])->save();

        return ['public' => $pub, 'private' => $priv];
    }

    public static function userActorUrl(User $user): string
    {
        return self::base().'/u/'.$user->id.'/actor';
    }

    public static function userHandle(User $user): string
    {
        return '@'.self::userUsername($user).'@'.self::host();
    }

    /** A member's ActivityPub actor document (a Person). */
    public static function userActor(User $user): array
    {
        $base = self::base();
        $url = self::userActorUrl($user);
        $avatar = $user->avatar_path;

        $doc = [
            '@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1'],
            'id' => $url,
            'type' => 'Person',
            'preferredUsername' => self::userUsername($user),
            'name' => Username::display($user->name, (int) $user->id),
            'summary' => $user->bio ? e(Str::limit(strip_tags((string) $user->bio), 400)) : '',
            'manuallyApprovesFollowers' => false,
            'discoverable' => true,
            'inbox' => $base.'/u/'.$user->id.'/inbox',
            'outbox' => $base.'/u/'.$user->id.'/outbox',
            'followers' => $base.'/u/'.$user->id.'/followers',
            'url' => $base.'/u/'.$user->id,
            'publicKey' => [
                'id' => $url.'#main-key',
                'owner' => $url,
                'publicKeyPem' => self::userKeys($user)['public'],
            ],
        ];
        if ($avatar) {
            $doc['icon'] = ['type' => 'Image', 'url' => str_starts_with($avatar, 'http') ? $avatar : $base.$avatar];
        }

        return $doc;
    }

    public static function userWebfinger(User $user): array
    {
        $url = self::userActorUrl($user);

        return [
            'subject' => 'acct:'.self::userUsername($user).'@'.self::host(),
            'aliases' => [$url, self::base().'/u/'.$user->id],
            'links' => [
                ['rel' => 'self', 'type' => self::CTYPE, 'href' => $url],
                ['rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => self::base().'/u/'.$user->id],
            ],
        ];
    }

    /** Resolve a webfinger username to the community OR a local member. */
    public static function resolveUsername(string $username): ?array
    {
        $username = ltrim(strtolower(trim($username)));
        if ($username === strtolower(self::username())) {
            return ['type' => 'community'];
        }
        $user = User::where('ap_username', $username)->where('is_federated', false)->first();

        return $user ? ['type' => 'user', 'user' => $user] : null;
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
    /** The member who authors federated content for a topic, or null = community. */
    public static function authorOf(?User $user): ?User
    {
        return ($user && ! $user->is_federated) ? $user : null;
    }

    /**
     * The standalone Note object for a topic. Served (with @context added) at
     * GET /t/{slug} under content negotiation so remotes can dereference a
     * boosted/replied object, and embedded inside the Create activity below.
     */
    public static function noteForTopic(Topic $topic): array
    {
        $base = self::base();
        $topicUrl = $base.'/t/'.$topic->slug;
        $author = self::authorOf($topic->user);
        $actor = $author ? self::userActorUrl($author) : self::actorUrl();
        $followers = $author ? $base.'/u/'.$author->id.'/followers' : $base.'/federation/followers';
        $published = ($topic->created_at ?? now())->toAtomString();
        $excerpt = trim(Str::limit(strip_tags((string) optional($topic->firstPost)->body_html), 280));
        $content = '<p><strong>'.e($topic->title).'</strong></p>'
            .($excerpt !== '' ? '<p>'.e($excerpt).'</p>' : '')
            .'<p><a href="'.e($topicUrl).'">'.e($topicUrl).'</a></p>';

        return [
            'id' => $topicUrl,
            'type' => 'Note',
            'attributedTo' => $actor,
            'content' => $content,
            'url' => $topicUrl,
            'published' => $published,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$followers],
        ];
    }

    public static function createActivityForTopic(Topic $topic): array
    {
        $note = self::noteForTopic($topic);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $note['id'].'#create',
            'type' => 'Create',
            'actor' => $note['attributedTo'],
            'published' => $note['published'],
            'to' => $note['to'],
            'cc' => $note['cc'],
            'object' => $note,
        ];
    }

    /**
     * The community boosts (Announce) a topic's Note to its own followers, so
     * anyone following @{community} sees every new topic with the member's
     * native author attribution preserved (Mastodon dereferences the Note).
     */
    public static function announceActivityForTopic(Topic $topic): array
    {
        $base = self::base();
        $noteUrl = $base.'/t/'.$topic->slug;
        $published = ($topic->created_at ?? now())->toAtomString();

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $noteUrl.'#announce',
            'type' => 'Announce',
            'actor' => self::actorUrl(),
            'published' => $published,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$base.'/federation/followers'],
            'object' => $noteUrl,
        ];
    }

    // ---- Phase 2: inbound replies + outbound cross-post ----

    /** Resolve an inReplyTo URL (one of our topic Notes) to the local Topic. */
    public static function topicFromUrl(?string $url): ?Topic
    {
        if (! $url) {
            return null;
        }
        $prefix = self::base().'/t/';
        if (! str_starts_with($url, $prefix)) {
            return null;
        }
        $slug = (string) strtok(trim(substr($url, strlen($prefix)), '/'), '#?');

        return $slug !== '' ? Topic::where('slug', $slug)->first() : null;
    }

    /** Find-or-create a local "federated" user mirroring a remote actor. */
    public static function upsertRemoteUser(string $actorUri): ?User
    {
        $actorUri = trim($actorUri);
        if ($actorUri === '') {
            return null;
        }
        $existing = User::where('federated_actor', $actorUri)->first();
        $doc = self::fetchActor($actorUri);
        if (! $doc && ! $existing) {
            return null;
        }

        if ($doc) {
            $username = (string) ($doc['preferredUsername'] ?? 'user');
            $host = (string) (parse_url((string) ($doc['id'] ?? $actorUri), PHP_URL_HOST) ?: 'remote');
            $icon = $doc['icon'] ?? null;
            $avatar = is_array($icon) ? ($icon['url'] ?? ($icon[0]['url'] ?? null)) : null;
            $fields = [
                'name' => Str::limit(strip_tags((string) ($doc['name'] ?? $username)), 80, ''),
                'federated_handle' => '@'.$username.'@'.$host,
                'federated_inbox' => $doc['endpoints']['sharedInbox'] ?? ($doc['inbox'] ?? null),
                'avatar_path' => is_string($avatar) ? $avatar : null,
                'is_federated' => true,
            ];
        } else {
            $fields = [];
        }

        $user = $existing ?: new User;
        $user->forceFill($fields);
        if (! $existing) {
            $user->forceFill([
                'federated_actor' => $actorUri,
                'email' => 'fedi-'.sha1($actorUri).'@federated.invalid',
                'password' => bcrypt(Str::random(32)),
                'is_federated' => true,
            ]);
            if (trim((string) $user->name) === '') {
                $user->name = 'fediverse user';
            }
        }
        $user->save();

        return $user;
    }

    /** Cross-post a local reply out to followers + remote thread participants. */
    public static function announceReply(Post $post, Topic $topic): void
    {
        try {
            if (! self::enabled() || ! \Illuminate\Support\Facades\Schema::hasTable('federation_followers')) {
                return;
            }
            $base = self::base();
            $author = self::authorOf($post->user);
            $actor = $author ? self::userActorUrl($author) : self::actorUrl();
            $followers = $author ? $base.'/u/'.$author->id.'/followers' : $base.'/federation/followers';
            $topicUrl = $base.'/t/'.$topic->slug;
            $postUrl = $topicUrl.'#post-'.$post->id;

            $remoteInboxes = User::whereIn('id', $topic->posts()->pluck('user_id'))
                ->where('is_federated', true)->pluck('federated_inbox')->filter()->all();
            $followerInboxes = DB::table('federation_followers')
                ->where(function ($q) use ($author) {
                    $q->whereNull('user_id');
                    if ($author) {
                        $q->orWhere('user_id', $author->id);
                    }
                })->get()->map(fn ($f) => $f->shared_inbox ?: $f->inbox)->filter()->all();
            $inboxes = array_values(array_unique(array_merge($followerInboxes, $remoteInboxes)));
            if (! $inboxes) {
                return;
            }

            $published = ($post->created_at ?? now())->toAtomString();
            // When the actor IS the author, no "X replied:" prefix is needed.
            $content = $author
                ? $post->body_html.'<p><a href="'.e($postUrl).'">'.e($topicUrl).'</a></p>'
                : '<p><strong>'.e(\App\Support\Username::display($post->user->name, (int) $post->user->id)).'</strong> '.__('replied').':</p>'.$post->body_html
                    .'<p><a href="'.e($postUrl).'">'.e($topicUrl).'</a></p>';

            $activity = [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $postUrl.'#create',
                'type' => 'Create',
                'actor' => $actor,
                'published' => $published,
                'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                'cc' => [$followers],
                'object' => [
                    'id' => $postUrl,
                    'type' => 'Note',
                    'attributedTo' => $actor,
                    'inReplyTo' => $topicUrl,
                    'content' => $content,
                    'url' => $postUrl,
                    'published' => $published,
                    'to' => ['https://www.w3.org/ns/activitystreams#Public'],
                    'cc' => [$followers],
                ],
            ];
            \App\Jobs\DeliverActivity::dispatch($activity, $inboxes, $author?->id)->afterCommit();
        } catch (\Throwable $e) {
            Log::debug('Federation reply cross-post skipped: '.$e->getMessage());
        }
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
            $author = self::authorOf($topic->user);
            $inboxesFor = fn ($rows) => $rows->map(fn ($f) => $f->shared_inbox ?: $f->inbox)
                ->filter()->unique()->values()->all();

            // Community followers (user_id NULL) get a boost from the community
            // actor — that's the actor they actually follow, so it lands in their
            // home timeline. Author attribution survives via the Note's attributedTo.
            $communityInboxes = $inboxesFor(DB::table('federation_followers')->whereNull('user_id')->get());
            if ($communityInboxes) {
                \App\Jobs\DeliverActivity::dispatch(self::announceActivityForTopic($topic), $communityInboxes, null)->afterCommit();
            }

            // The author's own followers get the per-user Create, signed by them.
            if ($author) {
                $authorInboxes = $inboxesFor(DB::table('federation_followers')->where('user_id', $author->id)->get());
                if ($authorInboxes) {
                    \App\Jobs\DeliverActivity::dispatch(self::createActivityForTopic($topic), $authorInboxes, $author->id)->afterCommit();
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Federation announce skipped: '.$e->getMessage());
        }
    }

    // ---- HTTP Signatures (draft-cavage, as Mastodon uses) ----

    /** Sign an outgoing request as the community actor. */
    public static function signHeaders(string $method, string $url, ?string $body = null): array
    {
        return self::signHeadersAs(null, $method, $url, $body);
    }

    /** Sign as a member ($signer) or the community ($signer null). */
    public static function signHeadersAs(?User $signer, string $method, string $url, ?string $body = null): array
    {
        $date = gmdate('D, d M Y H:i:s').' GMT';
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $method = strtolower($method);

        $headers = ['Host' => $host, 'Date' => $date];
        $signedHeaders = ['(request-target)', 'host', 'date'];
        $lines = [
            '(request-target): '.$method.' '.$path,
            'host: '.$host,
            'date: '.$date,
        ];

        if ($body !== null) {
            $digest = 'SHA-256='.base64_encode(hash('sha256', $body, true));
            $headers['Digest'] = $digest;
            $headers['Content-Type'] = self::CTYPE; // sent, but NOT signed
            $signedHeaders[] = 'digest';
            $lines[] = 'digest: '.$digest;
            // NB: don't sign content-type — Mastodon reconstructs that value
            // differently, which makes signature verification fail.
        }

        $priv = $signer ? self::userKeys($signer)['private'] : self::keys()['private'];
        $keyId = ($signer ? self::userActorUrl($signer) : self::actorUrl()).'#main-key';
        openssl_sign(implode("\n", $lines), $sig, $priv, OPENSSL_ALGO_SHA256);
        $headers['Signature'] = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $keyId,
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
