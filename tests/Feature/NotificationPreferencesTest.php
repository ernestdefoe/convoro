<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Contracts\TypedNotification;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_muted_type_is_not_delivered(): void
    {
        $user = User::factory()->create(['notification_prefs' => ['reaction' => false]]);

        Notifier::send($user, new StubReactionNotification);

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_unmuted_type_is_delivered(): void
    {
        $user = User::factory()->create(); // no prefs → everything on

        Notifier::send($user, new StubReactionNotification);

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_other_types_remain_on_when_one_is_muted(): void
    {
        $user = User::factory()->create(['notification_prefs' => ['reaction' => false]]);

        $this->assertFalse($user->wantsNotification('reaction'));
        $this->assertTrue($user->wantsNotification('reply'));
        $this->assertTrue($user->wantsNotification('mention'));
    }

    public function test_preferences_endpoint_saves_per_type_optout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/notifications/preferences', ['notifications' => ['reaction' => false]])
            ->assertRedirect();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->wantsNotification('reaction'));
        $this->assertTrue($fresh->wantsNotification('reply'));
    }

    public function test_preferences_endpoint_updates_digest_frequency(): void
    {
        $user = User::factory()->create(['digest_frequency' => 'weekly']);

        $this->actingAs($user)
            ->post('/notifications/preferences', ['digest_frequency' => 'daily'])
            ->assertRedirect();

        $this->assertSame('daily', $user->fresh()->digest_frequency);
    }

    public function test_preferences_endpoint_ignores_unknown_types(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/notifications/preferences', ['notifications' => ['bogus' => false]])
            ->assertRedirect();

        $this->assertSame([], (array) $user->fresh()->notification_prefs);
    }
}

/**
 * Minimal typed notification used to exercise Notifier mute logic without
 * constructing a full post/topic graph.
 */
class StubReactionNotification extends Notification implements TypedNotification
{
    public function type(): string
    {
        return 'reaction';
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'reaction', 'url' => '/'];
    }
}
