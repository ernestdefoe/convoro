<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use App\Models\User;
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

    /** WebFinger discovery: resolves acct:{user}@{host} to the community or a member. */
    public function webfinger(Request $request): JsonResponse
    {
        $this->guard();
        $resource = (string) $request->query('resource', '');
        if (! preg_match('/^acct:([^@]+)@(.+)$/i', $resource, $m) || strcasecmp($m[2], Federation::host()) !== 0) {
            abort(404);
        }
        $resolved = Federation::resolveUsername($m[1]);
        abort_unless($resolved !== null, 404);
        $doc = $resolved['type'] === 'user'
            ? Federation::userWebfinger($resolved['user'])
            : Federation::webfinger();

        return response()->json($doc)->header('Content-Type', 'application/jrd+json');
    }

    // ---- Per-user (Person) actors — Phase 3 ----

    public function userActor(User $user): JsonResponse
    {
        $this->guard();
        abort_if($user->is_federated, 404);

        return $this->ap(Federation::userActor($user));
    }

    public function userOutbox(User $user): JsonResponse
    {
        $this->guard();
        abort_if($user->is_federated, 404);
        $base = Federation::base();
        $topics = Topic::with('firstPost')->where('user_id', $user->id)->latest()->limit(20)->get()
            ->map(fn (Topic $t) => Federation::createActivityForTopic($t))->all();

        return $this->ap([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $base.'/u/'.$user->id.'/outbox',
            'type' => 'OrderedCollection',
            'totalItems' => Topic::where('user_id', $user->id)->count(),
            'orderedItems' => $topics,
        ]);
    }

    public function userFollowers(User $user): JsonResponse
    {
        $this->guard();
        abort_if($user->is_federated, 404);
        $items = \Illuminate\Support\Facades\DB::table('federation_followers')
            ->where('user_id', $user->id)->pluck('actor')->all();

        return $this->ap([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => Federation::base().'/u/'.$user->id.'/followers',
            'type' => 'OrderedCollection',
            'totalItems' => count($items),
            'orderedItems' => $items,
        ]);
    }

    public function userInbox(Request $request, User $user): Response
    {
        $this->guard();
        abort_if($user->is_federated, 404);

        return $this->processInbox($request, $user);
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

    /** Community inbox: verify the signature, then dispatch by activity type. */
    public function inbox(Request $request): Response
    {
        $this->guard();

        return $this->processInbox($request, null);
    }

    /** Shared inbox processing. $target = the followed member, or null = community. */
    private function processInbox(Request $request, ?User $target): Response
    {
        abort_unless(Federation::verifyRequest($request), 401, 'Invalid signature');

        $activity = $request->json()->all();
        $type = $activity['type'] ?? null;

        match ($type) {
            'Follow' => $this->handleFollow($activity, $target),
            'Create' => $this->handleCreate($activity),
            'Like' => $this->handleLike($activity),
            'Delete' => $this->handleDelete($activity),
            'Undo' => $this->handleUndo($activity, $target),
            default => null, // accepted but ignored
        };

        return response('', 202);
    }

    private function handleFollow(array $activity, ?User $target): void
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
            ['user_id' => $target?->id, 'actor' => $actorUri],
            [
                'inbox' => $remote['inbox'],
                'shared_inbox' => $remote['endpoints']['sharedInbox'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        // Acknowledge the follow, signed by whichever actor was followed.
        $localActor = $target ? Federation::userActorUrl($target) : Federation::actorUrl();
        $accept = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $localActor.'#accept/'.bin2hex(random_bytes(8)),
            'type' => 'Accept',
            'actor' => $localActor,
            'object' => $activity,
        ];
        \App\Jobs\DeliverActivity::dispatch($accept, [$remote['inbox']], $target?->id)->afterCommit();
    }

    private function handleUndo(array $activity, ?User $target): void
    {
        if (($activity['object']['type'] ?? null) === 'Follow') {
            $actor = (string) ($activity['actor'] ?? '');
            if ($actor !== '') {
                \Illuminate\Support\Facades\DB::table('federation_followers')
                    ->where('user_id', $target?->id)->where('actor', $actor)->delete();
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
