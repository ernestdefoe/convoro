<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every post a stable per-topic sequence number (1 = opening post) and
 * every topic a post_counter that issues them. This is what lets the topic
 * page load a *window* of posts around a position instead of the whole
 * thread — the difference between a 30-row query and hydrating a 10k-post
 * megathread into memory. Numbers are permanent: deleting a post leaves a
 * gap, so links and read positions never shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedInteger('post_counter')->default(0)->after('view_count');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('is_first');
        });

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // JOIN-update form: fastest on MySQL/MariaDB, and sidesteps the
            // "can't specify target table in FROM" restriction.
            DB::statement(<<<'SQL'
                UPDATE posts p
                JOIN (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY topic_id ORDER BY created_at, id) rn
                    FROM posts
                ) x ON x.id = p.id
                SET p.number = x.rn
            SQL);
        } else {
            // Portable form (SQLite demo tenants, pgsql).
            DB::statement(<<<'SQL'
                UPDATE posts SET number = (
                    SELECT rn FROM (
                        SELECT id, ROW_NUMBER() OVER (PARTITION BY topic_id ORDER BY created_at, id) rn
                        FROM posts
                    ) x WHERE x.id = posts.id
                )
            SQL);
        }

        DB::statement(<<<'SQL'
            UPDATE topics SET post_counter = COALESCE(
                (SELECT MAX(number) FROM posts WHERE posts.topic_id = topics.id), 0
            )
        SQL);

        Schema::table('posts', function (Blueprint $table) {
            $table->unique(['topic_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['topic_id', 'number']);
            $table->dropColumn('number');
        });
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('post_counter');
        });
    }
};
