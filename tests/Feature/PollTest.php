<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\Topic;
use App\Models\User;
use App\Support\Present;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PollTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_topic_with_a_poll(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/topics', [
            'title' => 'Best framework?',
            'body_html' => '<p>Vote below</p>',
            'poll' => [
                'question' => 'Which do you prefer?',
                'options' => ['Laravel', 'Symfony', '  '], // blank dropped
                'multiple' => false,
                'closes_days' => 7,
            ],
        ])->assertRedirect();

        $poll = Poll::first();
        $this->assertNotNull($poll);
        $this->assertSame('Which do you prefer?', $poll->question);
        $this->assertSame(1, $poll->max_choices, 'single choice');
        $this->assertNotNull($poll->closes_at);
        $this->assertSame(2, $poll->options()->count(), 'blank option dropped');
    }

    private function makePoll(int $maxChoices = 1): Poll
    {
        $topic = Topic::create(['title' => 'T', 'slug' => 't-'.uniqid(), 'user_id' => null, 'category_id' => null, 'last_post_at' => now()]);
        $poll = $topic->poll()->create(['question' => 'Pick', 'max_choices' => $maxChoices]);
        $poll->options()->create(['text' => 'A', 'position' => 0]);
        $poll->options()->create(['text' => 'B', 'position' => 1]);

        return $poll;
    }

    public function test_voting_records_and_reports_results(): void
    {
        $poll = $this->makePoll();
        $a = $poll->options[0]; $b = $poll->options[1];
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/polls/{$poll->id}/vote", ['options' => [$a->id]])
            ->assertOk()->assertJsonPath('poll.totalVotes', 1)->assertJsonPath('poll.hasVoted', true);

        $this->assertDatabaseHas('poll_votes', ['poll_id' => $poll->id, 'option_id' => $a->id, 'user_id' => $user->id]);
        $data = Present::poll($poll->fresh(), $user->id);
        $this->assertSame(100, collect($data['options'])->firstWhere('id', $a->id)['percent']);
    }

    public function test_changing_a_vote_replaces_the_previous_one(): void
    {
        $poll = $this->makePoll();
        $a = $poll->options[0]; $b = $poll->options[1];
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/polls/{$poll->id}/vote", ['options' => [$a->id]])->assertOk();
        $this->actingAs($user)->postJson("/polls/{$poll->id}/vote", ['options' => [$b->id]])->assertOk();

        $this->assertSame(1, DB::table('poll_votes')->where('poll_id', $poll->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('poll_votes', ['option_id' => $b->id, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('poll_votes', ['option_id' => $a->id, 'user_id' => $user->id]);
    }

    public function test_single_choice_poll_keeps_only_one(): void
    {
        $poll = $this->makePoll(1);
        $a = $poll->options[0]; $b = $poll->options[1];
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/polls/{$poll->id}/vote", ['options' => [$a->id, $b->id]])->assertOk();
        $this->assertSame(1, DB::table('poll_votes')->where('poll_id', $poll->id)->where('user_id', $user->id)->count());
    }

    public function test_closed_poll_rejects_votes(): void
    {
        $poll = $this->makePoll();
        $poll->update(['closes_at' => now()->subDay()]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/polls/{$poll->id}/vote", ['options' => [$poll->options->first()->id]])
            ->assertStatus(422);
    }
}
