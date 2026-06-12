<?php

namespace Tests\Feature;

use App\Support\FlarumImporter;
use App\Support\Importers\DiscourseImporter;
use App\Support\Importers\PhpbbImporter;
use App\Support\Importers\VbulletinImporter;
use App\Support\Importers\XenForoImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * End-to-end fixture tests for the source importers. Each builds a throwaway
 * SQLite database shaped like the real forum software's schema, runs the
 * importer against it, and asserts members/categories/topics/posts land in
 * Convoro correctly — exercising the mapping, first-post detection, reply
 * counts and BBCode/HTML conversion without needing a live source server.
 */
class ImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    private \Illuminate\Database\Connection $src;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = tempnam(sys_get_temp_dir(), 'imp_').'.sqlite';
        touch($this->fixturePath);
        config(['database.connections.fixture' => [
            'driver' => 'sqlite', 'database' => $this->fixturePath, 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('fixture');
        $this->src = DB::connection('fixture');
    }

    protected function tearDown(): void
    {
        DB::purge('fixture');
        @unlink($this->fixturePath);
        parent::tearDown();
    }

    private function cfg(array $extra = []): array
    {
        return array_merge([
            'driver' => 'sqlite', 'database' => $this->fixturePath,
            'host' => '', 'port' => null, 'username' => '', 'password' => '',
            'prefix' => '', 'source_url' => 'https://old.example',
        ], $extra);
    }

    private function s(): \Illuminate\Database\Schema\Builder
    {
        return $this->src->getSchemaBuilder();
    }

    public function test_flarum_import(): void
    {
        $this->s()->create('tags', function ($t) {
            $t->integer('id'); $t->string('name'); $t->string('slug'); $t->text('description')->nullable();
            $t->string('color')->nullable(); $t->integer('position')->nullable();
        });
        $this->s()->create('users', function ($t) {
            $t->integer('id'); $t->string('username'); $t->string('email'); $t->string('password');
            $t->string('avatar_url')->nullable(); $t->text('bio')->nullable(); $t->timestamp('joined_at')->nullable();
        });
        $this->s()->create('discussions', function ($t) {
            $t->integer('id'); $t->string('title'); $t->string('slug'); $t->integer('user_id')->nullable();
            $t->boolean('is_private')->default(false); $t->boolean('is_sticky')->default(false); $t->boolean('is_locked')->default(false);
            $t->timestamp('created_at')->nullable(); $t->timestamp('last_posted_at')->nullable();
        });
        $this->s()->create('discussion_tag', function ($t) { $t->integer('discussion_id'); $t->integer('tag_id'); });
        $this->s()->create('posts', function ($t) {
            $t->integer('id'); $t->integer('discussion_id'); $t->integer('user_id')->nullable(); $t->string('type')->default('comment');
            $t->integer('number')->default(1); $t->text('content')->nullable(); $t->boolean('is_private')->default(false);
            $t->timestamp('created_at')->nullable(); $t->timestamp('edited_at')->nullable();
        });
        $this->s()->create('post_likes', function ($t) { $t->integer('post_id'); $t->integer('user_id'); });

        $bcrypt = '$2y$10$abcdefghijklmnopqrstuv0123456789012345678901234567890u';
        // A primary tag (position set) → category; a secondary tag (position null) → tag.
        $this->src->table('tags')->insert([
            ['id' => 1, 'name' => 'General', 'slug' => 'general', 'description' => 'Main', 'color' => '#ff0000', 'position' => 0],
            ['id' => 2, 'name' => 'Announce', 'slug' => 'announce', 'description' => null, 'color' => null, 'position' => null],
        ]);
        $this->src->table('users')->insert([
            ['id' => 1, 'username' => 'Fiona', 'email' => 'fiona@fl.test', 'password' => $bcrypt, 'avatar_url' => null, 'bio' => 'hi', 'joined_at' => '2022-01-01 00:00:00'],
            ['id' => 2, 'username' => 'Gus', 'email' => 'gus@fl.test', 'password' => '', 'avatar_url' => null, 'bio' => null, 'joined_at' => '2022-01-02 00:00:00'],
        ]);
        $this->src->table('discussions')->insert([
            ['id' => 10, 'title' => 'Flarum topic', 'slug' => 'flarum-topic', 'user_id' => 1, 'is_private' => false, 'is_sticky' => true, 'is_locked' => false, 'created_at' => '2022-01-03 00:00:00', 'last_posted_at' => '2022-01-04 00:00:00'],
            ['id' => 11, 'title' => 'Secret DM', 'slug' => 'secret', 'user_id' => 1, 'is_private' => true, 'is_sticky' => false, 'is_locked' => false, 'created_at' => '2022-01-03 00:00:00', 'last_posted_at' => null],
        ]);
        $this->src->table('discussion_tag')->insert([
            ['discussion_id' => 10, 'tag_id' => 1], ['discussion_id' => 10, 'tag_id' => 2],
        ]);
        // s9e TextFormatter XML: rich (<r>) with bold tokens, and a plain (<t>).
        $this->src->table('posts')->insert([
            ['id' => 100, 'discussion_id' => 10, 'user_id' => 1, 'type' => 'comment', 'number' => 1, 'content' => '<r><p>Hello <STRONG><s>**</s>world<e>**</e></STRONG></p></r>', 'is_private' => false, 'created_at' => '2022-01-03 00:00:00', 'edited_at' => null],
            ['id' => 101, 'discussion_id' => 10, 'user_id' => 2, 'type' => 'comment', 'number' => 2, 'content' => '<t>just plain</t>', 'is_private' => false, 'created_at' => '2022-01-04 00:00:00', 'edited_at' => null],
            ['id' => 102, 'discussion_id' => 10, 'user_id' => 1, 'type' => 'discussionRenamed', 'number' => 3, 'content' => null, 'is_private' => false, 'created_at' => '2022-01-04 00:00:00', 'edited_at' => null],
        ]);
        $this->src->table('post_likes')->insert([['post_id' => 100, 'user_id' => 2]]);

        $summary = FlarumImporter::run($this->cfg(['flarum_url' => 'https://old.example']), ['tags' => true], fn () => null);

        $this->assertSame(1, $summary['categories'], 'primary tag → category');
        $this->assertSame(1, $summary['tags'], 'secondary tag → tag');
        $this->assertSame(2, $summary['users']);
        $this->assertSame(1, $summary['topics'], 'private discussion skipped');
        $this->assertSame(2, $summary['posts'], 'only comment posts');
        $this->assertSame(1, $summary['reactions'], 'like → reaction');

        $this->assertDatabaseHas('categories', ['name' => 'General', 'color' => '#ff0000']);
        $this->assertDatabaseHas('users', ['name' => 'Fiona', 'email' => 'fiona@fl.test', 'password' => $bcrypt]);
        $topic = DB::table('topics')->where('title', 'Flarum topic')->first();
        $this->assertNotNull($topic);
        $this->assertSame(1, (int) $topic->is_pinned);
        $this->assertSame(1, (int) $topic->reply_count);
        $this->assertStringContainsString('<strong>world</strong>', DB::table('posts')->where('topic_id', $topic->id)->where('is_first', true)->value('body_html'));
        $this->assertDatabaseMissing('topics', ['title' => 'Secret DM']);
    }

    public function test_xenforo_import(): void
    {
        $this->s()->create('xf_node', function ($t) {
            $t->integer('node_id'); $t->string('title'); $t->string('node_type_id');
            $t->text('description')->nullable(); $t->integer('display_order')->default(0);
        });
        $this->s()->create('xf_user', function ($t) {
            $t->integer('user_id'); $t->string('username'); $t->string('email');
            $t->integer('register_date')->default(0); $t->integer('avatar_date')->default(0);
        });
        $this->s()->create('xf_user_authenticate', function ($t) { $t->integer('user_id'); $t->text('data'); });
        $this->s()->create('xf_thread', function ($t) {
            $t->integer('thread_id'); $t->integer('node_id'); $t->integer('user_id'); $t->string('title');
            $t->string('discussion_state')->default('visible'); $t->integer('sticky')->default(0);
            $t->integer('view_count')->default(0); $t->integer('post_date')->default(0); $t->integer('last_post_date')->default(0);
        });
        $this->s()->create('xf_post', function ($t) {
            $t->integer('post_id'); $t->integer('thread_id'); $t->integer('user_id'); $t->integer('position')->default(0);
            $t->string('message_state')->default('visible'); $t->text('message'); $t->integer('post_date')->default(0);
        });

        $this->src->table('xf_node')->insert([
            ['node_id' => 1, 'title' => 'General', 'node_type_id' => 'Forum', 'description' => 'Chat', 'display_order' => 1],
            ['node_id' => 2, 'title' => 'Category Holder', 'node_type_id' => 'Category', 'description' => null, 'display_order' => 0],
        ]);
        $bcrypt = '$2y$10$abcdefghijklmnopqrstuv0123456789012345678901234567890u';
        $this->src->table('xf_user_authenticate')->insert([
            ['user_id' => 1, 'data' => serialize(['hash' => $bcrypt, 'hashFunc' => 'password_hash'])],
        ]);
        $this->src->table('xf_user')->insert([
            ['user_id' => 1, 'username' => 'Ada', 'email' => 'ada@xf.test', 'register_date' => 1600000000, 'avatar_date' => 1600000001],
            ['user_id' => 2, 'username' => 'Bob', 'email' => 'bob@xf.test', 'register_date' => 1600000000, 'avatar_date' => 0],
        ]);
        $this->src->table('xf_thread')->insert([
            ['thread_id' => 10, 'node_id' => 1, 'user_id' => 1, 'title' => 'Hello XF', 'discussion_state' => 'visible', 'sticky' => 1, 'view_count' => 7, 'post_date' => 1600000100, 'last_post_date' => 1600000200],
            ['thread_id' => 11, 'node_id' => 1, 'user_id' => 2, 'title' => 'Hidden', 'discussion_state' => 'deleted', 'sticky' => 0, 'view_count' => 0, 'post_date' => 1600000100, 'last_post_date' => 1600000100],
        ]);
        $this->src->table('xf_post')->insert([
            ['post_id' => 100, 'thread_id' => 10, 'user_id' => 1, 'position' => 0, 'message_state' => 'visible', 'message' => '[b]Hi[/b] all', 'post_date' => 1600000100],
            ['post_id' => 101, 'thread_id' => 10, 'user_id' => 2, 'position' => 1, 'message_state' => 'visible', 'message' => 'Reply here', 'post_date' => 1600000200],
            ['post_id' => 102, 'thread_id' => 11, 'user_id' => 2, 'position' => 0, 'message_state' => 'visible', 'message' => 'in hidden', 'post_date' => 1600000100],
        ]);

        $summary = XenForoImporter::run($this->cfg(), [], fn () => null);

        $this->assertSame(1, $summary['categories'], 'only the Forum node becomes a category');
        $this->assertSame(2, $summary['users']);
        $this->assertSame(1, $summary['topics'], 'deleted thread skipped');
        $this->assertSame(2, $summary['posts'], 'only posts of the visible thread');

        $this->assertDatabaseHas('categories', ['name' => 'General']);
        $this->assertDatabaseHas('users', ['name' => 'Ada', 'email' => 'ada@xf.test', 'password' => $bcrypt]);
        $topic = DB::table('topics')->where('title', 'Hello XF')->first();
        $this->assertNotNull($topic);
        $this->assertSame(1, (int) $topic->is_pinned);
        $this->assertSame(1, (int) $topic->reply_count, '2 posts → 1 reply');
        $this->assertStringContainsString('<strong>Hi</strong>', DB::table('posts')->where('topic_id', $topic->id)->where('is_first', true)->value('body_html'));
        $this->assertDatabaseMissing('topics', ['title' => 'Hidden']);
    }

    public function test_phpbb_import(): void
    {
        $this->s()->create('phpbb_forums', function ($t) {
            $t->integer('forum_id'); $t->string('forum_name'); $t->text('forum_desc')->nullable(); $t->integer('left_id')->default(0);
        });
        $this->s()->create('phpbb_users', function ($t) {
            $t->integer('user_id'); $t->string('username'); $t->string('user_email'); $t->string('user_password');
            $t->integer('user_type')->default(0); $t->integer('user_regdate')->default(0);
        });
        $this->s()->create('phpbb_topics', function ($t) {
            $t->integer('topic_id'); $t->integer('forum_id'); $t->integer('topic_poster'); $t->string('topic_title');
            $t->integer('topic_visibility')->default(1); $t->integer('topic_type')->default(0); $t->integer('topic_views')->default(0);
            $t->integer('topic_time')->default(0); $t->integer('topic_last_post_time')->default(0);
        });
        $this->s()->create('phpbb_posts', function ($t) {
            $t->integer('post_id'); $t->integer('topic_id'); $t->integer('poster_id'); $t->text('post_text');
            $t->string('bbcode_uid')->default(''); $t->integer('post_visibility')->default(1); $t->integer('post_time')->default(0);
        });

        $this->src->table('phpbb_forums')->insert([['forum_id' => 1, 'forum_name' => 'Support', 'forum_desc' => 'Help', 'left_id' => 1]]);
        $this->src->table('phpbb_users')->insert([
            ['user_id' => 2, 'username' => 'Cara', 'user_email' => 'cara@pb.test', 'user_password' => '$H$9oldphpass', 'user_type' => 0, 'user_regdate' => 1500000000],
            ['user_id' => 3, 'username' => 'Bot', 'user_email' => 'bot@pb.test', 'user_password' => 'x', 'user_type' => 2, 'user_regdate' => 1500000000],
        ]);
        $this->src->table('phpbb_topics')->insert([
            ['topic_id' => 5, 'forum_id' => 1, 'topic_poster' => 2, 'topic_title' => 'phpBB Q', 'topic_visibility' => 1, 'topic_type' => 1, 'topic_views' => 3, 'topic_time' => 1500000100, 'topic_last_post_time' => 1500000200],
            ['topic_id' => 6, 'forum_id' => 1, 'topic_poster' => 2, 'topic_title' => 'Soft deleted', 'topic_visibility' => 0, 'topic_type' => 0, 'topic_views' => 0, 'topic_time' => 1500000100, 'topic_last_post_time' => 1500000100],
        ]);
        // phpBB stores tags as [b:uid]…[/b:uid] and the text HTML-escaped.
        $this->src->table('phpbb_posts')->insert([
            ['post_id' => 50, 'topic_id' => 5, 'poster_id' => 2, 'post_text' => '[b:abc]Bold[/b:abc] &amp; co', 'bbcode_uid' => 'abc', 'post_visibility' => 1, 'post_time' => 1500000100],
            ['post_id' => 51, 'topic_id' => 5, 'poster_id' => 2, 'post_text' => 'second', 'bbcode_uid' => '', 'post_visibility' => 1, 'post_time' => 1500000200],
        ]);

        $summary = PhpbbImporter::run($this->cfg(['prefix' => 'phpbb_']), [], fn () => null);

        $this->assertSame(1, $summary['categories']);
        $this->assertSame(1, $summary['users'], 'bot (user_type 2) skipped');
        $this->assertSame(1, $summary['topics'], 'soft-deleted topic skipped');
        $this->assertSame(2, $summary['posts']);
        $this->assertDatabaseHas('categories', ['name' => 'Support']);
        $this->assertDatabaseHas('users', ['email' => 'cara@pb.test']);
        $topic = DB::table('topics')->where('title', 'phpBB Q')->first();
        $this->assertSame(1, (int) $topic->is_pinned);
        $first = DB::table('posts')->where('topic_id', $topic->id)->where('is_first', true)->value('body_html');
        $this->assertStringContainsString('<strong>Bold</strong>', $first);
        $this->assertDatabaseMissing('users', ['email' => 'bot@pb.test']);
    }

    public function test_discourse_import(): void
    {
        $this->s()->create('categories', function ($t) {
            $t->integer('id'); $t->string('name'); $t->text('description')->nullable();
            $t->string('color')->nullable(); $t->integer('position')->default(0);
        });
        $this->s()->create('users', function ($t) {
            $t->integer('id'); $t->string('username'); $t->string('name')->nullable(); $t->string('email')->nullable(); $t->timestamp('created_at')->nullable();
        });
        $this->s()->create('topics', function ($t) {
            $t->integer('id'); $t->integer('category_id')->nullable(); $t->integer('user_id'); $t->string('title');
            $t->string('archetype')->default('regular'); $t->integer('views')->default(0);
            $t->timestamp('pinned_at')->nullable(); $t->timestamp('deleted_at')->nullable();
            $t->timestamp('created_at')->nullable(); $t->timestamp('last_posted_at')->nullable();
        });
        $this->s()->create('posts', function ($t) {
            $t->integer('id'); $t->integer('topic_id'); $t->integer('user_id'); $t->integer('post_number')->default(1);
            $t->integer('post_type')->default(1); $t->text('cooked'); $t->timestamp('deleted_at')->nullable(); $t->timestamp('created_at')->nullable();
        });

        $this->src->table('categories')->insert([['id' => 1, 'name' => 'Lounge', 'description' => 'Hang out', 'color' => '0088CC', 'position' => 1]]);
        $this->src->table('users')->insert([
            ['id' => -1, 'username' => 'system', 'name' => 'system', 'email' => 'system@d.test', 'created_at' => '2020-01-01 00:00:00'],
            ['id' => 1, 'username' => 'Dana', 'name' => 'Dana D', 'email' => 'dana@d.test', 'created_at' => '2021-01-01 00:00:00'],
        ]);
        $this->src->table('topics')->insert([
            ['id' => 7, 'category_id' => 1, 'user_id' => 1, 'title' => 'Discourse topic', 'archetype' => 'regular', 'views' => 9, 'pinned_at' => '2021-02-01 00:00:00', 'deleted_at' => null, 'created_at' => '2021-01-02 00:00:00', 'last_posted_at' => '2021-01-03 00:00:00'],
            ['id' => 8, 'category_id' => 1, 'user_id' => 1, 'title' => 'A private message', 'archetype' => 'private_message', 'views' => 0, 'pinned_at' => null, 'deleted_at' => null, 'created_at' => '2021-01-02 00:00:00', 'last_posted_at' => null],
            ['id' => 9, 'category_id' => 1, 'user_id' => 1, 'title' => 'Deleted topic', 'archetype' => 'regular', 'views' => 0, 'pinned_at' => null, 'deleted_at' => '2021-05-01 00:00:00', 'created_at' => '2021-01-02 00:00:00', 'last_posted_at' => null],
        ]);
        $this->src->table('posts')->insert([
            ['id' => 70, 'topic_id' => 7, 'user_id' => 1, 'post_number' => 1, 'post_type' => 1, 'cooked' => '<p>Hello <b>world</b></p>', 'deleted_at' => null, 'created_at' => '2021-01-02 00:00:00'],
            ['id' => 71, 'topic_id' => 7, 'user_id' => 1, 'post_number' => 2, 'post_type' => 1, 'cooked' => '<p>reply<script>alert(1)</script></p>', 'deleted_at' => null, 'created_at' => '2021-01-03 00:00:00'],
            ['id' => 72, 'topic_id' => 7, 'user_id' => 1, 'post_number' => 3, 'post_type' => 3, 'cooked' => '<p>small action</p>', 'deleted_at' => null, 'created_at' => '2021-01-03 00:00:00'],
        ]);

        $summary = DiscourseImporter::run($this->cfg(), [], fn () => null);

        $this->assertSame(1, $summary['categories']);
        $this->assertSame(1, $summary['users'], 'system user id<=0 skipped');
        $this->assertSame(1, $summary['topics'], 'PM + deleted skipped');
        $this->assertSame(2, $summary['posts'], 'small-action post_type 3 skipped');
        $this->assertDatabaseHas('categories', ['name' => 'Lounge', 'color' => '#0088CC']);
        $this->assertDatabaseHas('users', ['email' => 'dana@d.test']);
        $body = DB::table('posts')->where('is_first', true)->value('body_html');
        $this->assertStringContainsString('<b>world</b>', $body);
        $reply = DB::table('posts')->where('is_first', false)->value('body_html');
        $this->assertStringNotContainsString('<script', $reply, 'cooked HTML is sanitised');
    }

    public function test_vbulletin_import(): void
    {
        $this->s()->create('forum', function ($t) {
            $t->integer('forumid'); $t->string('title'); $t->text('description')->nullable(); $t->integer('displayorder')->default(0);
        });
        $this->s()->create('user', function ($t) {
            $t->integer('userid'); $t->string('username'); $t->string('email'); $t->integer('joindate')->default(0);
        });
        $this->s()->create('thread', function ($t) {
            $t->integer('threadid'); $t->integer('forumid'); $t->integer('postuserid'); $t->string('title');
            $t->integer('visible')->default(1); $t->integer('sticky')->default(0); $t->integer('views')->default(0);
            $t->integer('open')->default(1); $t->integer('dateline')->default(0); $t->integer('lastpost')->default(0);
        });
        $this->s()->create('post', function ($t) {
            $t->integer('postid'); $t->integer('threadid'); $t->integer('userid'); $t->text('pagetext');
            $t->integer('visible')->default(1); $t->integer('dateline')->default(0);
        });

        $this->src->table('forum')->insert([['forumid' => 1, 'title' => 'Lobby', 'description' => 'Talk', 'displayorder' => 1]]);
        $this->src->table('user')->insert([['userid' => 4, 'username' => 'Evan', 'email' => 'evan@vb.test', 'joindate' => 1400000000]]);
        $this->src->table('thread')->insert([
            ['threadid' => 3, 'forumid' => 1, 'postuserid' => 4, 'title' => 'vB thread', 'visible' => 1, 'sticky' => 1, 'views' => 5, 'open' => 1, 'dateline' => 1400000100, 'lastpost' => 1400000200],
            ['threadid' => 4, 'forumid' => 1, 'postuserid' => 4, 'title' => 'Moderated', 'visible' => 0, 'sticky' => 0, 'views' => 0, 'open' => 1, 'dateline' => 1400000100, 'lastpost' => 1400000100],
        ]);
        $this->src->table('post')->insert([
            ['postid' => 30, 'threadid' => 3, 'userid' => 4, 'pagetext' => '[i]Italic[/i] start', 'visible' => 1, 'dateline' => 1400000100],
            ['postid' => 31, 'threadid' => 3, 'userid' => 4, 'pagetext' => 'next', 'visible' => 1, 'dateline' => 1400000200],
        ]);

        $summary = VbulletinImporter::run($this->cfg(), [], fn () => null);

        $this->assertSame(1, $summary['categories']);
        $this->assertSame(1, $summary['users']);
        $this->assertSame(1, $summary['topics'], 'non-visible thread skipped');
        $this->assertSame(2, $summary['posts']);
        $this->assertDatabaseHas('categories', ['name' => 'Lobby']);
        $topic = DB::table('topics')->where('title', 'vB thread')->first();
        $this->assertSame(1, (int) $topic->is_pinned);
        $this->assertSame(1, (int) $topic->reply_count);
        $this->assertStringContainsString('<em>Italic</em>', DB::table('posts')->where('topic_id', $topic->id)->where('is_first', true)->value('body_html'));
    }
}
