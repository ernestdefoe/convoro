<?php

namespace Tests\Feature;

use App\Mail\InviteEmail;
use App\Models\Invite;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_a_shareable_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/invites/link')->assertRedirect();

        $link = $user->invites()->whereNull('email')->first();
        $this->assertNotNull($link);
        $this->assertSame((int) Settings::get('invites.link_max_uses'), $link->max_uses);
    }

    public function test_creating_a_link_twice_reuses_the_existing_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/invites/link');
        $this->actingAs($user)->post('/invites/link');

        $this->assertSame(1, $user->invites()->whereNull('email')->count());
    }

    public function test_member_can_send_an_email_invite(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/invites/email', ['email' => 'friend@example.com'])->assertRedirect();

        $this->assertDatabaseHas('invites', ['inviter_id' => $user->id, 'email' => 'friend@example.com']);
        Mail::assertQueued(InviteEmail::class, fn (InviteEmail $m) => $m->hasTo('friend@example.com'));
    }

    public function test_cannot_invite_an_existing_member(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)->post('/invites/email', ['email' => 'taken@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_invite_quota_is_enforced(): void
    {
        Settings::set('invites.max_per_user', 1);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/invites/email', ['email' => 'one@example.com'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/invites/email', ['email' => 'two@example.com'])->assertSessionHasErrors('email');
    }

    public function test_join_page_validates_a_good_code_and_stashes_it(): void
    {
        $inviter = User::factory()->create();
        $invite = Invite::create(['inviter_id' => $inviter->id, 'code' => 'goodcode', 'max_uses' => 5]);

        $this->get('/join/'.$invite->code)
            ->assertOk()
            ->assertSessionHas('invite_code', 'goodcode');
    }

    public function test_join_page_rejects_an_exhausted_code(): void
    {
        $inviter = User::factory()->create();
        Invite::create(['inviter_id' => $inviter->id, 'code' => 'spent', 'max_uses' => 1, 'uses' => 1]);

        $this->get('/join/spent')->assertOk()->assertSessionMissing('invite_code');
    }

    public function test_registering_with_a_link_credits_inviter_and_notifies(): void
    {
        $inviter = User::factory()->create();
        $invite = Invite::create(['inviter_id' => $inviter->id, 'code' => 'linky', 'max_uses' => 10]);

        $this->post('/register', [
            'name' => 'New Person',
            'email' => 'newperson@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite' => 'linky',
        ])->assertRedirect();

        $new = User::where('email', 'newperson@example.com')->firstOrFail();
        $this->assertSame($inviter->id, $new->invited_by);
        $this->assertSame(1, $invite->fresh()->uses);
        $this->assertSame(1, $inviter->notifications()->count());
    }

    public function test_email_bound_invite_only_accepts_that_email(): void
    {
        $inviter = User::factory()->create();
        $invite = Invite::create(['inviter_id' => $inviter->id, 'code' => 'bound', 'email' => 'bound@example.com', 'max_uses' => 1]);

        // Wrong email → invite not consumed
        $this->post('/register', [
            'name' => 'Wrong', 'email' => 'wrong@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'invite' => 'bound',
        ]);
        $this->assertSame(0, $invite->fresh()->uses);
        $this->assertNull(User::where('email', 'wrong@example.com')->first()->invited_by);
    }

    public function test_only_owner_can_revoke(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $invite = Invite::create(['inviter_id' => $owner->id, 'code' => 'mine', 'max_uses' => 1]);

        $this->actingAs($other)->delete('/invites/'.$invite->id)->assertForbidden();
        $this->assertDatabaseHas('invites', ['id' => $invite->id]);

        $this->actingAs($owner)->delete('/invites/'.$invite->id)->assertRedirect();
        $this->assertDatabaseMissing('invites', ['id' => $invite->id]);
    }

    public function test_invites_can_be_disabled(): void
    {
        Settings::set('invites.enabled', false);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/invites/link')->assertForbidden();
        $this->actingAs($user)->post('/invites/email', ['email' => 'x@example.com'])->assertForbidden();
    }
}
