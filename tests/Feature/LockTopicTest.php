<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LockTopicTest extends TestCase
{
    use RefreshDatabase;

    private function topic(): Topic
    {
        return Topic::create(['title' => 'T', 'slug' => 't-'.uniqid(), 'user_id' => null, 'category_id' => null, 'last_post_at' => now()]);
    }

    public function test_regular_member_cannot_lock(): void
    {
        $t = $this->topic();
        $this->actingAs(User::factory()->create())->post('/t/'.$t->slug.'/lock')->assertForbidden();
        $this->assertFalse((bool) $t->fresh()->is_locked);
    }

    public function test_admin_can_lock_and_unlock(): void
    {
        $t = $this->topic();
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/t/'.$t->slug.'/lock')->assertRedirect();
        $this->assertTrue((bool) $t->fresh()->is_locked);
        $this->actingAs($admin)->post('/t/'.$t->slug.'/lock')->assertRedirect();
        $this->assertFalse((bool) $t->fresh()->is_locked);
    }

    public function test_locked_topic_rejects_replies(): void
    {
        $t = $this->topic();
        $t->update(['is_locked' => true]);
        $this->actingAs(User::factory()->create())
            ->post('/t/'.$t->slug.'/posts', ['body_html' => '<p>hi</p>'])
            ->assertForbidden();
    }
}
