<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ForumSeeder extends Seeder
{
    public function run(): void
    {
        // Admin (Ernest)
        User::updateOrCreate(
            ['email' => 'ernestdefoe@gmail.com'],
            ['name' => 'Ernest Defoe', 'password' => bcrypt('password'), 'is_admin' => true]
        );

        // Users
        $names = ['Riley West', 'Ana Kovač', 'Devin Tran', 'Mara Pinto', 'Jules Marin', 'Sam Lee'];
        $users = collect($names)->map(fn ($n, $i) => User::firstOrCreate(
            ['email' => Str::slug($n, '.') . '@convoro.test'],
            ['name' => $n, 'password' => bcrypt('password')]
        ));

        // Categories
        $cats = collect([
            ['name' => 'Announcements', 'icon' => 'fa-solid fa-bullhorn', 'color' => '#6366f1'],
            ['name' => 'Development', 'icon' => 'fa-solid fa-code', 'color' => '#2563eb'],
            ['name' => 'Design & Themes', 'icon' => 'fa-solid fa-palette', 'color' => '#16a34a'],
            ['name' => 'Extensions', 'icon' => 'fa-solid fa-puzzle-piece', 'color' => '#7c3aed'],
            ['name' => 'Support', 'icon' => 'fa-solid fa-circle-question', 'color' => '#e8830c'],
            ['name' => 'Off-topic', 'icon' => 'fa-solid fa-mug-hot', 'color' => '#64748b'],
        ])->map(fn ($c, $i) => Category::firstOrCreate(
            ['slug' => Str::slug($c['name'])],
            $c + ['position' => $i]
        ));

        // Tags
        $tags = collect(['feature', 'guide', 'help', 'bug', 'showcase'])->map(fn ($t) => Tag::firstOrCreate(
            ['slug' => Str::slug($t)],
            ['name' => Str::title($t)]
        ));

        $seed = [
            ['Convoro 1.0 is here — what’s new and the road ahead', 'Announcements', 0, true, true,
                '<p>Today we’re shipping <strong>Convoro 1.0</strong> — a modern community platform that takes the best of what we love and leaves the friction behind.</p><blockquote>A true WYSIWYG composer, live threads, a one-click extension marketplace and a live theme editor — no markdown, no fuss.</blockquote><p>Drop your questions below — we’re answering live. 🎉</p>',
                ['feature', 'guide']],
            ['Show off your theme editor creations 🎨', 'Design & Themes', 1, false, false,
                '<p>Drop a screenshot of your customized Convoro theme. The live editor makes it ridiculously easy.</p>', ['showcase']],
            ['How do I migrate from XenForo? Step-by-step', 'Support', 2, false, false,
                '<p>Putting together a clean import path. Users, posts, reactions and attachments all came over — attachments auto-converted to WebP which cut storage by ~60%.</p>', ['guide', 'help']],
            ['Built a Spotify now-playing extension in an afternoon', 'Extensions', 3, false, false,
                '<p>The extension API + click-to-install made this trivial. Source is up — install it straight from the marketplace.</p>', ['showcase', 'feature']],
            ['Convoro installs as a PWA on iOS & Android', 'Announcements', 4, false, false,
                '<p>Push notifications, offline reading, one app icon — no app store needed.</p>', ['feature']],
            ['Friday game night thread 🎮', 'Off-topic', 5, false, true,
                '<p>Live thread — drop in, we’re playing now.</p>', []],
            ['Feature request: scheduled topics', 'Development', 1, false, false,
                '<p>Would love to schedule a topic to publish at a set time. Anyone else want this?</p>', ['feature']],
            ['Bug: reaction picker overflows on mobile', 'Support', 2, false, false,
                '<p>On a 360px viewport the reaction popover clips at the edge. Repro steps inside.</p>', ['bug', 'help']],
        ];

        foreach ($seed as $i => [$title, $catName, $authorIdx, $pinned, $live, $html, $topicTags]) {
            $cat = $cats->firstWhere('name', $catName);
            $author = $users[$authorIdx % $users->count()];
            $when = Carbon::now()->subHours(($i + 1) * 5);

            $topic = Topic::create([
                'title' => $title,
                'slug' => Str::slug($title) . '-' . Str::lower(Str::random(4)),
                'user_id' => $author->id,
                'category_id' => $cat->id,
                'cover_image' => 'https://picsum.photos/seed/convoro' . $i . '/600/300',
                'is_pinned' => $pinned,
                'is_live' => $live,
                'created_at' => $when,
                'last_post_at' => $when,
                'view_count' => random_int(800, 48000),
            ]);

            $topic->tags()->sync($tags->whereIn('slug', collect($topicTags)->map(fn ($t) => Str::slug($t)))->pluck('id'));

            $first = Post::create([
                'topic_id' => $topic->id, 'user_id' => $author->id,
                'body_html' => $html, 'is_first' => true, 'created_at' => $when, 'updated_at' => $when,
            ]);

            // a couple of replies
            $replyCount = random_int(1, 4);
            for ($r = 0; $r < $replyCount; $r++) {
                $ru = $users[($authorIdx + $r + 1) % $users->count()];
                $rwhen = $when->copy()->addMinutes(($r + 1) * 17);
                Post::create([
                    'topic_id' => $topic->id, 'user_id' => $ru->id,
                    'body_html' => '<p>' . ['Love this!', 'Following 👀', 'This is exactly what I needed.', 'Huge +1 from me.', 'Can’t wait to try it.'][$r % 5] . '</p>',
                    'created_at' => $rwhen, 'updated_at' => $rwhen,
                ]);
            }
            $topic->update(['reply_count' => $replyCount, 'last_post_at' => $when->copy()->addMinutes(($replyCount) * 17)]);

            // reactions on the first post
            foreach ($users->take(random_int(2, $users->count())) as $ru) {
                Reaction::firstOrCreate([
                    'post_id' => $first->id, 'user_id' => $ru->id,
                    'emoji' => ['👍', '🎉', '🔥', '❤️', '😮'][random_int(0, 4)],
                ]);
            }
        }
    }
}
