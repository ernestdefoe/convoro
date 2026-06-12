<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PinTopicTest extends TestCase
{
    use RefreshDatabase;

    private function topic(string $title, $lastPost): Topic
    {
        return Topic::create([
            'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'user_id' => null, 'category_id' => null, 'last_post_at' => $lastPost,
        ]);
    }

    public function test_regular_member_cannot_pin(): void
    {
        $t = $this->topic('A', now());
        $this->actingAs(User::factory()->create())->post('/t/'.$t->slug.'/pin')->assertForbidden();
        $this->assertFalse((bool) $t->fresh()->is_pinned);
    }

    public function test_admin_can_pin_and_unpin(): void
    {
        $t = $this->topic('A', now());
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/t/'.$t->slug.'/pin')->assertRedirect();
        $this->assertTrue((bool) $t->fresh()->is_pinned);

        $this->actingAs($admin)->post('/t/'.$t->slug.'/pin')->assertRedirect();
        $this->assertFalse((bool) $t->fresh()->is_pinned);
    }

    public function test_pinned_topic_sorts_to_the_top(): void
    {
        $older = $this->topic('Older but pinned', now()->subDays(3));
        $newer = $this->topic('Newer not pinned', now());
        $older->update(['is_pinned' => true]);

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('topics.data.0.title', 'Older but pinned')
            ->where('topics.data.0.isPinned', true));
    }
}
