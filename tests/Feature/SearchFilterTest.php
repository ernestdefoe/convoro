<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

    private function topic(string $title, Category $cat, User $author, $lastPost, int $replies): Topic
    {
        return Topic::create([
            'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'user_id' => $author->id, 'category_id' => $cat->id,
            'last_post_at' => $lastPost, 'reply_count' => $replies,
        ]);
    }

    private function seedData(): array
    {
        $a = Category::create(['name' => 'Alpha', 'slug' => 'alpha', 'color' => '#111111']);
        $b = Category::create(['name' => 'Beta', 'slug' => 'beta', 'color' => '#222222']);
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $t1 = $this->topic('Recent Alpha by Alice', $a, $alice, now()->subDay(), 5);
        $t2 = $this->topic('Old Beta by Bob', $b, $bob, now()->subMonths(2), 0);

        return compact('a', 'b', 'alice', 'bob', 't1', 't2');
    }

    public function test_filter_by_category(): void
    {
        $this->seedData();
        $this->get('/search?category=alpha')
            ->assertInertia(fn (Assert $p) => $p->component('Search')->has('results', 1)
                ->where('results.0.title', 'Recent Alpha by Alice'));
    }

    public function test_filter_by_author(): void
    {
        $this->seedData();
        $this->get('/search?author=bob') // case-insensitive
            ->assertInertia(fn (Assert $p) => $p->has('results', 1)
                ->where('results.0.title', 'Old Beta by Bob'));
    }

    public function test_filter_by_date_window(): void
    {
        $this->seedData();
        $this->get('/search?when=week')
            ->assertInertia(fn (Assert $p) => $p->has('results', 1)
                ->where('results.0.title', 'Recent Alpha by Alice'));
    }

    public function test_sort_by_replies(): void
    {
        $this->seedData();
        // Browse within a date window, ordered by most replies.
        $this->get('/search?when=year&sort=replies')
            ->assertInertia(fn (Assert $p) => $p->has('results', 2)
                ->where('results.0.title', 'Recent Alpha by Alice'));
    }

    public function test_no_query_no_filters_shows_nothing(): void
    {
        $this->seedData();
        $this->get('/search')
            ->assertInertia(fn (Assert $p) => $p->has('results', 0));
    }
}
