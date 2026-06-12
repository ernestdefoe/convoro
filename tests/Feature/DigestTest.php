<?php

namespace Tests\Feature;

use App\Mail\DigestEmail;
use App\Models\User;
use App\Notifications\ReplyNotification;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class DigestTest extends TestCase
{
    use RefreshDatabase;

    /** Give a user one unread notification so the digest has content to send. */
    private function giveUnread(User $user): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ReplyNotification::class,
            'data' => ['type' => 'reply', 'url' => '/'],
            'read_at' => null,
        ]);
    }

    public function test_digest_queues_for_subscribed_user_with_content(): void
    {
        Mail::fake();
        $user = User::factory()->create(['digest_frequency' => 'daily']);
        $this->giveUnread($user);

        $this->artisan('convoro:digest daily')->assertSuccessful();

        Mail::assertQueued(DigestEmail::class, fn (DigestEmail $m) => $m->hasTo($user->email));
        $this->assertNotNull($user->fresh()->last_digest_at);
    }

    public function test_digest_skips_other_frequencies(): void
    {
        Mail::fake();
        $weekly = User::factory()->create(['digest_frequency' => 'weekly']);
        $this->giveUnread($weekly);
        $off = User::factory()->create(['digest_frequency' => 'off']);
        $this->giveUnread($off);

        $this->artisan('convoro:digest daily')->assertSuccessful();

        Mail::assertNotQueued(DigestEmail::class);
    }

    public function test_digest_respects_master_switch(): void
    {
        Mail::fake();
        Settings::set('digests.enabled', false);
        $user = User::factory()->create(['digest_frequency' => 'daily']);
        $this->giveUnread($user);

        $this->artisan('convoro:digest daily')->assertSuccessful();

        Mail::assertNotQueued(DigestEmail::class);
    }

    public function test_digest_skips_users_with_no_content(): void
    {
        Mail::fake();
        User::factory()->create(['digest_frequency' => 'daily']); // no topics, no unread

        $this->artisan('convoro:digest daily')->assertSuccessful();

        Mail::assertNotQueued(DigestEmail::class);
    }

    public function test_signed_unsubscribe_link_turns_digest_off(): void
    {
        $user = User::factory()->create(['digest_frequency' => 'daily']);

        $url = URL::signedRoute('digest.unsubscribe', ['user' => $user->id]);
        $this->get($url)->assertOk()->assertSee('unsubscribed', false);

        $this->assertSame('off', $user->fresh()->digest_frequency);
    }

    public function test_unsigned_unsubscribe_is_forbidden(): void
    {
        $user = User::factory()->create(['digest_frequency' => 'daily']);

        $this->get('/digest/unsubscribe/'.$user->id)->assertForbidden();

        $this->assertSame('daily', $user->fresh()->digest_frequency);
    }
}
