<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Support\LiveTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LiveTopicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function topic(): Topic
    {
        return Topic::create([
            'title' => 'Live one', 'slug' => 'live-'.uniqid(), 'user_id' => null,
            'category_id' => null, 'last_post_at' => now(),
        ]);
    }

    public function test_topic_is_live_only_with_enough_viewers(): void
    {
        $t = $this->topic();
        LiveTopics::heartbeat($t->id, 1);
        $this->assertNotContains($t->id, LiveTopics::liveIds([$t->id]), 'one viewer is not live');

        LiveTopics::heartbeat($t->id, 2);
        $this->assertContains($t->id, LiveTopics::liveIds([$t->id]), 'two viewers → live');
        $this->assertSame(2, LiveTopics::count($t->id));
    }

    public function test_heartbeat_endpoint_requires_auth(): void
    {
        $t = $this->topic();
        $this->post('/t/'.$t->slug.'/heartbeat')->assertRedirect(); // guests are bounced to login
        $this->assertSame(0, LiveTopics::count($t->id));
    }

    public function test_heartbeat_endpoint_records_a_viewer(): void
    {
        $t = $this->topic();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/t/'.$t->slug.'/heartbeat')
            ->assertOk()->assertJson(['count' => 1, 'live' => false]);
        $this->assertSame(1, LiveTopics::count($t->id));
    }

    public function test_live_topics_endpoint_lists_live_ids(): void
    {
        $t = $this->topic();
        LiveTopics::heartbeat($t->id, 11);
        LiveTopics::heartbeat($t->id, 22);
        $this->getJson('/api/live-topics?ids='.$t->id.',999')
            ->assertOk()->assertJson(['live' => [$t->id]]);
    }

    public function test_forum_index_flags_a_live_topic(): void
    {
        $t = $this->topic();
        LiveTopics::heartbeat($t->id, 7);
        LiveTopics::heartbeat($t->id, 8);

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('topics.data.0.title', 'Live one')
            ->where('topics.data.0.isLive', true));
    }
}
