<?php

namespace Tests\Feature;

use App\Models\Draft;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_save_a_draft(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/drafts', [
            'title' => 'Work in progress', 'body_html' => '<p>half written</p>',
        ])->assertRedirect('/drafts');

        $this->assertDatabaseHas('drafts', ['user_id' => $user->id, 'title' => 'Work in progress', 'scheduled_at' => null]);
    }

    public function test_member_can_schedule_a_post(): void
    {
        $user = User::factory()->create();
        $when = now()->addDay()->format('Y-m-d\TH:i');
        $this->actingAs($user)->post('/drafts', [
            'title' => 'Future news', 'body_html' => '<p>soon</p>', 'scheduled_at' => $when,
        ])->assertRedirect('/drafts');

        $draft = Draft::where('user_id', $user->id)->first();
        $this->assertNotNull($draft->scheduled_at);
        $this->assertTrue($draft->isScheduled());
    }

    public function test_publishing_a_draft_creates_a_topic_and_removes_the_draft(): void
    {
        $user = User::factory()->create();
        $draft = Draft::create(['user_id' => $user->id, 'title' => 'Ready to go', 'body_html' => '<p>published body</p>']);

        $this->actingAs($user)->post("/drafts/{$draft->id}/publish")->assertRedirect();

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
        $topic = Topic::where('title', 'Ready to go')->first();
        $this->assertNotNull($topic);
        $this->assertSame($user->id, $topic->user_id);
        $this->assertSame(1, $topic->posts()->count());
    }

    public function test_scheduler_publishes_due_posts_only(): void
    {
        $user = User::factory()->create();
        $due = Draft::create(['user_id' => $user->id, 'title' => 'Due now', 'body_html' => '<p>x</p>', 'scheduled_at' => now()->subMinute()]);
        $later = Draft::create(['user_id' => $user->id, 'title' => 'Later', 'body_html' => '<p>y</p>', 'scheduled_at' => now()->addDay()]);

        Artisan::call('convoro:publish-scheduled');

        $this->assertDatabaseMissing('drafts', ['id' => $due->id]);
        $this->assertDatabaseHas('topics', ['title' => 'Due now']);
        $this->assertDatabaseHas('drafts', ['id' => $later->id]);
        $this->assertDatabaseMissing('topics', ['title' => 'Later']);
    }

    public function test_publishing_a_topic_with_draft_id_clears_the_draft(): void
    {
        $user = User::factory()->create();
        $draft = Draft::create(['user_id' => $user->id, 'title' => 'D', 'body_html' => '<p>d</p>']);

        $this->actingAs($user)->post('/topics', [
            'title' => 'Published from draft', 'body_html' => '<p>final body</p>', 'draft_id' => $draft->id,
        ])->assertRedirect();

        $this->assertDatabaseMissing('drafts', ['id' => $draft->id]);
        $this->assertDatabaseHas('topics', ['title' => 'Published from draft']);
    }

    public function test_cannot_touch_another_members_draft(): void
    {
        $draft = Draft::create(['user_id' => User::factory()->create()->id, 'title' => 'Private', 'body_html' => '<p>x</p>']);
        $other = User::factory()->create();

        $this->actingAs($other)->post("/drafts/{$draft->id}/publish")->assertForbidden();
        $this->actingAs($other)->delete("/drafts/{$draft->id}")->assertForbidden();
        $this->assertDatabaseHas('drafts', ['id' => $draft->id]);
    }
}
