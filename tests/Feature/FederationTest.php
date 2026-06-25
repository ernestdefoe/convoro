<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Support\Federation;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FederationTest extends TestCase
{
    use RefreshDatabase;

    private function keysOrSkip(): void
    {
        Settings::set('federation.enabled', true);
        try {
            Federation::keys();
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenSSL key generation unavailable on this host.');
        }
    }

    public function test_endpoints_are_404_when_disabled(): void
    {
        Settings::set('federation.enabled', false);
        $this->getJson('/federation/actor')->assertNotFound();
        $this->getJson('/.well-known/webfinger?resource=acct:community@'.Federation::host())->assertNotFound();
    }

    public function test_webfinger_and_actor_when_enabled(): void
    {
        $this->keysOrSkip();

        $this->get('/.well-known/webfinger?resource='.Federation::webfinger()['subject'])
            ->assertOk()
            ->assertJsonPath('subject', Federation::webfinger()['subject'])
            ->assertJsonPath('links.0.href', Federation::actorUrl());

        $this->get('/federation/actor')
            ->assertOk()
            ->assertJsonPath('type', 'Group')
            ->assertJsonPath('id', Federation::actorUrl())
            ->assertJsonPath('publicKey.id', Federation::actorUrl().'#main-key')
            ->assertJsonPath('assertionMethod.0.type', 'Multikey');
    }

    public function test_http_signature_round_trip(): void
    {
        $this->keysOrSkip();

        // The "remote" actor we verify against is ourselves: fake the actor fetch.
        Http::fake([Federation::actorUrl() => Http::response(Federation::actor(), 200)]);

        $body = json_encode(['type' => 'Follow', 'actor' => Federation::actorUrl(), 'object' => Federation::actorUrl()]);
        $headers = Federation::signHeaders('post', Federation::base().'/federation/inbox', $body);

        // Replay the signed request through the inbox; a valid signature → 202.
        $this->call('POST', '/federation/inbox', [], [], [], [
            'HTTP_SIGNATURE' => $headers['Signature'],
            'HTTP_HOST' => $headers['Host'],
            'HTTP_DATE' => $headers['Date'],
            'HTTP_DIGEST' => $headers['Digest'],
            'CONTENT_TYPE' => Federation::CTYPE,
        ], $body)->assertStatus(202);

        $this->assertDatabaseHas('federation_followers', ['actor' => Federation::actorUrl()]);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $this->keysOrSkip();
        Settings::set('federation.enabled', true);

        $this->call('POST', '/federation/inbox', [], [], [], [
            'HTTP_SIGNATURE' => 'keyId="x",headers="(request-target)",signature="bm9wZQ=="',
            'CONTENT_TYPE' => Federation::CTYPE,
        ], '{"type":"Follow"}')->assertStatus(401);
    }

    public function test_topic_from_url_resolves_local_topic(): void
    {
        $user = User::factory()->create();
        $topic = Topic::create(['title' => 'Hello world', 'slug' => 'hello-world-1', 'user_id' => $user->id, 'last_post_at' => now()]);

        $this->assertSame($topic->id, Federation::topicFromUrl(Federation::base().'/t/hello-world-1')?->id);
        $this->assertNull(Federation::topicFromUrl('https://elsewhere.test/t/hello-world-1'));
        $this->assertNull(Federation::topicFromUrl(null));
    }

    public function test_inbound_remote_reply_creates_federated_post(): void
    {
        $this->keysOrSkip();
        $user = User::factory()->create();
        $topic = Topic::create(['title' => 'Federated topic', 'slug' => 'federated-topic-9', 'user_id' => $user->id, 'last_post_at' => now()]);
        $topicUrl = Federation::base().'/t/federated-topic-9';
        $alice = 'https://remote.test/users/alice';

        Http::fake([
            Federation::actorUrl() => Http::response(Federation::actor(), 200),
            $alice => Http::response([
                'id' => $alice, 'type' => 'Person', 'preferredUsername' => 'alice', 'name' => 'Alice',
                'inbox' => $alice.'/inbox',
            ], 200),
        ]);

        $body = json_encode([
            'type' => 'Create', 'actor' => $alice,
            'object' => ['id' => 'https://remote.test/notes/1', 'type' => 'Note', 'inReplyTo' => $topicUrl, 'content' => '<p>Great topic!</p>', 'published' => now()->toAtomString()],
        ]);
        $headers = Federation::signHeaders('post', Federation::base().'/federation/inbox', $body);

        $this->call('POST', '/federation/inbox', [], [], [], [
            'HTTP_SIGNATURE' => $headers['Signature'], 'HTTP_HOST' => $headers['Host'],
            'HTTP_DATE' => $headers['Date'], 'HTTP_DIGEST' => $headers['Digest'], 'CONTENT_TYPE' => Federation::CTYPE,
        ], $body)->assertStatus(202);

        $this->assertDatabaseHas('users', ['federated_actor' => $alice, 'is_federated' => true]);
        $this->assertDatabaseHas('posts', ['topic_id' => $topic->id, 'federated_object' => 'https://remote.test/notes/1']);
        $this->assertSame(1, (int) $topic->fresh()->reply_count);
    }

    public function test_per_user_username_is_stable_and_deduped(): void
    {
        Settings::set('federation.enabled', true);
        $a = User::factory()->create(['name' => 'Jane Doe']);
        $b = User::factory()->create(['name' => 'Jane Doe']);

        $ua = Federation::userUsername($a);
        $this->assertSame($ua, Federation::userUsername($a->fresh())); // stable once stored
        $this->assertNotSame($ua, Federation::userUsername($b));       // clash gets disambiguated
        $this->assertNotSame(strtolower(Federation::username()), $ua); // never collides with community
        $this->assertSame($ua, strtolower($ua));                       // stored lowercased
    }

    public function test_webfinger_routes_to_a_member(): void
    {
        Settings::set('federation.enabled', true);
        $user = User::factory()->create(['name' => 'Federated Member']);
        $handle = Federation::userUsername($user);

        $this->get('/.well-known/webfinger?resource=acct:'.$handle.'@'.Federation::host())
            ->assertOk()
            ->assertJsonPath('subject', 'acct:'.$handle.'@'.Federation::host())
            ->assertJsonPath('links.0.href', Federation::userActorUrl($user));

        // A federated (remote) user is never resolvable as a local handle.
        $remote = User::factory()->create(['is_federated' => true]);
        $remote->forceFill(['ap_username' => 'ghost'])->save();
        $this->get('/.well-known/webfinger?resource=acct:ghost@'.Federation::host())->assertNotFound();
    }

    public function test_per_user_actor_document(): void
    {
        $this->keysOrSkip();
        $user = User::factory()->create(['name' => 'Federated Member']);

        $this->get('/u/'.$user->id.'/actor')
            ->assertOk()
            ->assertJsonPath('type', 'Person')
            ->assertJsonPath('id', Federation::userActorUrl($user))
            ->assertJsonPath('inbox', Federation::base().'/u/'.$user->id.'/inbox')
            ->assertJsonPath('publicKey.id', Federation::userActorUrl($user).'#main-key');
    }
}
