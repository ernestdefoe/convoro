<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Mentions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MentionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_linkify_turns_handle_into_a_profile_link(): void
    {
        $u = User::factory()->create(['name' => 'Riley West']);

        $html = Mentions::linkify('<p>Hi @riley.west!</p>');

        $this->assertStringContainsString('<a class="mention" href="/u/'.$u->id.'">@Riley West</a>', $html);
    }

    public function test_linkify_matches_first_name_too(): void
    {
        $u = User::factory()->create(['name' => 'Riley West']);

        $html = Mentions::linkify('<p>thanks @riley</p>');

        $this->assertStringContainsString('href="/u/'.$u->id.'"', $html);
    }

    public function test_linkify_leaves_unknown_handles_and_emails_alone(): void
    {
        User::factory()->create(['name' => 'Riley West']);

        $html = Mentions::linkify('<p>email me at bob@example.com or @nobody</p>');

        $this->assertStringNotContainsString('class="mention"', $html);
        $this->assertStringContainsString('bob@example.com', $html);
    }

    public function test_linkify_does_not_rewrite_inside_existing_links(): void
    {
        User::factory()->create(['name' => 'Riley West']);

        $html = Mentions::linkify('<p><a href="/x">@riley.west</a></p>');

        // Exactly one anchor — the existing link, not a nested mention link.
        $this->assertSame(1, substr_count($html, '<a '));
    }

    public function test_linkify_fast_path_when_no_at_sign(): void
    {
        User::factory()->create(['name' => 'Riley West']);

        $this->assertSame('<p>hello world</p>', Mentions::linkify('<p>hello world</p>'));
    }
}
