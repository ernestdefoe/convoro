<?php

namespace Tests\Feature;

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
            ->assertJsonPath('type', 'Service')
            ->assertJsonPath('id', Federation::actorUrl())
            ->assertJsonPath('publicKey.id', Federation::actorUrl().'#main-key');
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
}
