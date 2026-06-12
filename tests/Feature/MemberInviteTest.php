<?php

namespace Tests\Feature;

use App\Mail\InviteEmail;
use App\Models\Invite;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberInviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_gets_a_referral_link(): void
    {
        $user = User::factory()->create();
        $res = $this->actingAs($user)->get('/invite');
        $res->assertOk();
        // A reusable personal referral invite was created for them.
        $this->assertDatabaseHas('invites', ['created_by' => $user->id, 'note' => 'referral']);
    }

    public function test_member_can_email_invites(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/invite/send', [
            'emails' => ['friend@example.com', 'friend@example.com', 'two@example.com'],
            'message' => 'come join',
        ])->assertRedirect();

        // Deduped to 2 unique addresses; an email queued for each.
        $this->assertSame(2, Invite::where('created_by', $user->id)->whereNotNull('email')->count());
        Mail::assertQueued(InviteEmail::class, 2);
    }

    public function test_signup_through_a_members_code_is_attributed(): void
    {
        $inviter = User::factory()->create();
        $invite = Invite::create([
            'code' => 'REF12345', 'created_by' => $inviter->id, 'email' => null,
            'note' => 'referral', 'max_uses' => 1000000, 'expires_at' => null,
        ]);

        $this->post('/register', [
            'name' => 'New Friend',
            'email' => 'newfriend@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite' => 'ref12345', // case-insensitive
        ]);

        $newUser = User::where('email', 'newfriend@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertSame($inviter->id, $newUser->invited_by);
        $this->assertSame(1, $invite->fresh()->uses);
    }

    public function test_invite_page_blocked_when_disabled(): void
    {
        Settings::set('invites.members_enabled', false);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/invite')->assertForbidden();
    }
}
