<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Models\User;
use App\Support\Present;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnreadTopicTest extends TestCase
{
    use RefreshDatabase;

    private function topic(string $title, $lastPostAt): Topic
    {
        return Topic::create([
            'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'user_id' => null, 'category_id' => null, 'last_post_at' => $lastPostAt,
        ]);
    }

    public function test_unread_logic(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(5)]);

        $old = $this->topic('Before I joined', now()->subDays(10));   // older than join → not new
        $fresh = $this->topic('New since I joined', now()->subDay()); // after join, never read → new

        $unread = Present::unreadTopicIds([$old, $fresh], $user);
        $this->assertNotContains($old->id, $unread);
        $this->assertContains($fresh->id, $unread);

        // Open it (mark read) → no longer new.
        DB::table('topic_reads')->updateOrInsert(
            ['user_id' => $user->id, 'topic_id' => $fresh->id],
            ['last_read_at' => now()],
        );
        $this->assertNotContains($fresh->id, Present::unreadTopicIds([$fresh], $user));

        // A new reply after reading → new again.
        $fresh->update(['last_post_at' => now()->addMinute()]);
        $this->assertContains($fresh->id, Present::unreadTopicIds([$fresh], $user));
    }

    public function test_guests_have_no_unread(): void
    {
        $t = $this->topic('Anything', now());
        $this->assertSame([], Present::unreadTopicIds([$t], null));
    }

    public function test_viewing_a_topic_marks_it_read(): void
    {
        $user = User::factory()->create();
        $t = $this->topic('Read me', now());
        $this->actingAs($user)->get('/t/'.$t->slug)->assertOk();
        $this->assertDatabaseHas('topic_reads', ['user_id' => $user->id, 'topic_id' => $t->id]);
    }
}
