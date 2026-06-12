<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Support\Federation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ActivityPub endpoints. All off (404) unless federation is enabled in admin.
 */
class FederationController extends Controller
{
    private function guard(): void
    {
        abort_unless(Federation::enabled(), 404);
    }

    private function ap(array $doc): JsonResponse
    {
        return response()->json($doc)->header('Content-Type', Federation::CTYPE);
    }

    /** WebFinger discovery: resolves acct:{user}@{host} to the actor. */
    public function webfinger(Request $request): JsonResponse
    {
        $this->guard();
        $resource = (string) $request->query('resource', '');
        $want = 'acct:'.Federation::username().'@'.Federation::host();
        abort_unless(strcasecmp($resource, $want) === 0, 404);

        return response()->json(Federation::webfinger())->header('Content-Type', 'application/jrd+json');
    }

    public function nodeinfo(): JsonResponse
    {
        $this->guard();

        return response()->json([
            'links' => [[
                'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
                'href' => Federation::base().'/nodeinfo/2.0',
            ]],
        ]);
    }

    public function nodeinfoData(): JsonResponse
    {
        $this->guard();

        return response()->json([
            'version' => '2.0',
            'software' => ['name' => 'convoro', 'version' => (string) config('convoro.version')],
            'protocols' => ['activitypub'],
            'services' => ['inbound' => [], 'outbound' => []],
            'openRegistrations' => ! (bool) \App\Support\Settings::get('invites.only', false),
            'usage' => ['users' => ['total' => \App\Models\User::count()], 'localPosts' => Topic::count()],
            'metadata' => ['nodeName' => (string) (\App\Support\Settings::get('site.name') ?: 'Convoro')],
        ]);
    }

    public function actor(): JsonResponse
    {
        $this->guard();

        return $this->ap(Federation::actor());
    }

    /** Outbox: recent topics as Create activities. */
    public function outbox(): JsonResponse
    {
        $this->guard();
        $items = Topic::with('firstPost')->latest()->limit(20)->get()
            ->map(fn (Topic $t) => Federation::createActivityForTopic($t))->all();

        return $this->ap([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => Federation::base().'/federation/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => Topic::count(),
            'orderedItems' => $items,
        ]);
    }

    public function followers(): JsonResponse
    {
        $this->guard();
        $items = \Illuminate\Support\Facades\DB::table('federation_followers')->pluck('actor')->all();

        return $this->ap([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => Federation::base().'/federation/followers',
            'type' => 'OrderedCollection',
            'totalItems' => count($items),
            'orderedItems' => $items,
        ]);
    }

    /** Inbox: verify the signature, then handle Follow / Undo(Follow). */
    public function inbox(Request $request): Response
    {
        $this->guard();
        abort_unless(Federation::verifyRequest($request), 401, 'Invalid signature');

        $activity = $request->json()->all();
        $type = $activity['type'] ?? null;

        match ($type) {
            'Follow' => $this->handleFollow($activity),
            'Create' => $this->handleCreate($activity),
            'Like' => $this->handleLike($activity),
            'Delete' => $this->handleDelete($activity),
            'Undo' => $this->handleUndo($activity),
            default => null, // accepted but ignored
        };

        return response('', 202);
    }

    private function handleFollow(array $activity): void
    {
        $actorUri = (string) ($activity['actor'] ?? '');
        if ($actorUri === '') {
            return;
        }
        $remote = Federation::fetchActor($actorUri);
        if (! $remote || empty($remote['inbox'])) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('federation_followers')->updateOrInsert(
            ['actor' => $actorUri],
            [
                'inbox' => $remote['inbox'],
                'shared_inbox' => $remote['endpoints']['sharedInbox'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // Acknowledge the follow.
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => Federation::actorUrl().'#accept/'.bin2hex(random_bytes(8)),
            'type' => 'Accept',
            'actor' => Federation::actorUrl(),
            'object' => $activity,
        ];
        \App\Jobs\DeliverActivity::dispatch($accept, [$remote['inbox']])->afterCommit();
    }

    private function handleUndo(array $activity): void
    {
        if (($activity['object']['type'] ?? null) === 'Follow') {
            $actor = (string) ($activity['actor'] ?? '');
            if ($actor !== '') {
                \Illuminate\Support\Facades\DB::table('federation_followers')->where('actor', $actor)->delete();
            }
        }
    }

    /** A remote reply to one of our topics → a federated Post in that topic. */
    private function handleCreate(array $activity): void
    {
        $obj = $activity['object'] ?? null;
        if (! is_array($obj)) {
            return;
        }
        $topic = Federation::topicFromUrl($obj['inReplyTo'] ?? null);
        if (! $topic) {
            return; // not a reply to our content
        }
        $objectId = (string) ($obj['id'] ?? $activity['id'] ?? '');
        if ($objectId === '' || \App\Models\Post::where('federated_object', $objectId)->exists()) {
            return; // missing id or already imported
        }
        $author = Federation::upsertRemoteUser((string) ($activity['actor'] ?? ''));
        if (! $author) {
            return;
        }
        $html = \App\Support\Content::clean((string) ($obj['content'] ?? ''));
        if (trim(strip_tags($html)) === '') {
            return;
        }
        $created = isset($obj['published']) ? \Illuminate\Support\Carbon::parse($obj['published']) : now();

        $post = new \App\Models\Post;
        $post->forceFill([
            'topic_id' => $topic->id,
            'user_id' => $author->id,
            'body_html' => $html,
            'is_first' => false,
            'federated_object' => $objectId,
            'created_at' => $created,
            'updated_at' => now(),
        ])->save();

        $topic->increment('reply_count');
        $topic->update(['last_post_at' => now()]);

        $post->load(['user', 'reactions']);
        broadcast(new \App\Events\PostCreated(\App\Support\Present::post($post, null), $topic->id));
    }

    /** A remote Like on one of our topics → a 👍 reaction on its first post. */
    private function handleLike(array $activity): void
    {
        $obj = $activity['object'] ?? null;
        $url = is_string($obj) ? $obj : (string) ($obj['id'] ?? '');
        $topic = Federation::topicFromUrl($url);
        if (! $topic || ! $topic->firstPost) {
            return;
        }
        $author = Federation::upsertRemoteUser((string) ($activity['actor'] ?? ''));
        if (! $author) {
            return;
        }
        \Illuminate\Support\Facades\DB::table('reactions')->insertOrIgnore([
            'post_id' => $topic->firstPost->id, 'user_id' => $author->id, 'emoji' => '👍',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** A remote Delete → remove the federated post it refers to. */
    private function handleDelete(array $activity): void
    {
        $obj = $activity['object'] ?? null;
        $id = is_string($obj) ? $obj : (string) ($obj['id'] ?? '');
        if ($id !== '') {
            \App\Models\Post::where('federated_object', $id)->delete();
        }
    }
}
