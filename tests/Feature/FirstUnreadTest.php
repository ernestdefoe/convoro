<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FirstUnreadTest extends TestCase
{
    use RefreshDatabase;

    private function makeTopic(User $author): array
    {
        $topic = Topic::create(['title' => 'Unread test', 'slug' => 'unread-test-'.uniqid(), 'user_id' => $author->id, 'last_post_at' => now()]);
        $topic->posts()->create(['user_id' => $author->id, 'body_html' => '<p>first</p>', 'is_first' => true, 'created_at' => now()->subDays(3)]);
        $r1 = $topic->posts()->create(['user_id' => $author->id, 'body_html' => '<p>reply one</p>', 'is_first' => false, 'created_at' => now()->subDays(2)]);
        $r2 = $topic->posts()->create(['user_id' => $author->id, 'body_html' => '<p>reply two</p>', 'is_first' => false, 'created_at' => now()->subHour()]);

        return [$topic, $r1, $r2];
    }

    public function test_jumps_to_first_unread_reply(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        [$topic, $r1, $r2] = $this->makeTopic($author);

        // Reader last opened the topic between r1 and r2 → r2 is the first unread.
        DB::table('topic_reads')->insert(['user_id' => $reader->id, 'topic_id' => $topic->id, 'last_read_at' => now()->subDay()]);

        $this->actingAs($reader)->get('/t/'.$topic->slug)
            ->assertInertia(fn ($p) => $p->component('Topic/Show')->where('firstUnreadId', $r2->id));
    }

    public function test_caught_up_reader_has_no_first_unread(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        [$topic] = $this->makeTopic($author);

        DB::table('topic_reads')->insert(['user_id' => $reader->id, 'topic_id' => $topic->id, 'last_read_at' => now()]);

        $this->actingAs($reader)->get('/t/'.$topic->slug)
            ->assertInertia(fn ($p) => $p->where('firstUnreadId', null));
    }

    public function test_first_time_reader_has_no_first_unread(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        [$topic] = $this->makeTopic($author);

        // No topic_reads row at all → first visit → land at the top (null).
        $this->actingAs($reader)->get('/t/'.$topic->slug)
            ->assertInertia(fn ($p) => $p->where('firstUnreadId', null));
    }
}
