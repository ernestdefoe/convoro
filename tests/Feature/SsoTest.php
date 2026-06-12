<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoTest extends TestCase
{
    use RefreshDatabase;

    private function configureSso(array $overrides = []): void
    {
        Cache::flush();
        Settings::setMany(array_merge([
            'sso.enabled' => true,
            'sso.label' => 'Okta',
            'sso.issuer' => 'https://idp.test',
            'sso.client_id' => 'client-abc',
            'sso.client_secret' => 'secret-xyz',
            'sso.scopes' => 'openid profile email',
            'sso.auto_create' => true,
            'sso.allowed_domain' => '',
        ], $overrides));
    }

    private function fakeProvider(array $profile): void
    {
        Http::fake([
            'idp.test/.well-known/openid-configuration' => Http::response([
                'authorization_endpoint' => 'https://idp.test/authorize',
                'token_endpoint' => 'https://idp.test/token',
                'userinfo_endpoint' => 'https://idp.test/userinfo',
            ]),
            'idp.test/token' => Http::response(['access_token' => 'AT', 'token_type' => 'Bearer']),
            'idp.test/userinfo' => Http::response($profile),
        ]);
    }

    public function test_redirect_sends_to_provider_and_stores_state(): void
    {
        $this->configureSso();
        Http::fake(['idp.test/.well-known/openid-configuration' => Http::response([
            'authorization_endpoint' => 'https://idp.test/authorize', 'token_endpoint' => 'https://idp.test/token',
        ])]);

        $res = $this->get('/auth/oidc/redirect');
        $res->assertRedirect();
        $this->assertStringStartsWith('https://idp.test/authorize?', $res->headers->get('Location'));
        $this->assertStringContainsString('client_id=client-abc', $res->headers->get('Location'));
    }

    public function test_callback_creates_and_logs_in_a_new_member(): void
    {
        $this->configureSso();
        $this->fakeProvider(['sub' => 'okta-123', 'email' => 'New.User@idp.test', 'name' => 'New User']);

        $res = $this->withSession(['oidc.state' => 'state-1'])
            ->get('/auth/oidc/callback?state=state-1&code=the-code');

        $res->assertRedirect();
        $this->assertAuthenticated();
        $user = User::where('email', 'new.user@idp.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('okta-123', $user->oidc_sub);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_callback_logs_in_existing_member_by_email(): void
    {
        $this->configureSso();
        $existing = User::factory()->create(['email' => 'member@idp.test']);
        $this->fakeProvider(['sub' => 'okta-999', 'email' => 'member@idp.test', 'name' => 'Member']);

        $this->withSession(['oidc.state' => 's'])->get('/auth/oidc/callback?state=s&code=c')->assertRedirect();
        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame('okta-999', $existing->fresh()->oidc_sub);
    }

    public function test_state_mismatch_is_rejected(): void
    {
        $this->configureSso();
        $this->fakeProvider(['sub' => 'x', 'email' => 'x@idp.test']);
        $this->withSession(['oidc.state' => 'real'])->get('/auth/oidc/callback?state=forged&code=c');
        $this->assertGuest();
    }

    public function test_domain_restriction_blocks_others(): void
    {
        $this->configureSso(['sso.allowed_domain' => 'company.com']);
        $this->fakeProvider(['sub' => 'y', 'email' => 'someone@idp.test']);
        $this->withSession(['oidc.state' => 's'])->get('/auth/oidc/callback?state=s&code=c');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'someone@idp.test']);
    }

    public function test_disabled_returns_404(): void
    {
        Settings::set('sso.enabled', false);
        $this->get('/auth/oidc/redirect')->assertNotFound();
    }

    public function test_admin_saves_settings_without_clobbering_secret(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Settings::set('sso.client_secret', 'original-secret');

        $this->actingAs($admin)->post('/admin/sso', [
            'enabled' => true, 'label' => 'Auth0', 'issuer' => 'https://acme.auth0.com',
            'client_id' => 'cid', 'client_secret' => '', 'scopes' => 'openid email',
            'auto_create' => true, 'allowed_domain' => '@Acme.com',
        ])->assertRedirect();

        $this->assertSame('Auth0', Settings::get('sso.label'));
        $this->assertSame('original-secret', Settings::get('sso.client_secret'), 'blank secret leaves the stored one intact');
        $this->assertSame('acme.com', Settings::get('sso.allowed_domain'), 'domain normalized');
    }
}
