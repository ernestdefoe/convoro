<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Full-text indexes powering "Ask Convoro" retrieval (and better search).
 * MySQL/MariaDB only — guarded so sqlite/pgsql installs migrate cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (! self::hasIndex('posts', 'posts_body_fulltext')) {
            Schema::table('posts', fn (Blueprint $t) => $t->fullText('body_html', 'posts_body_fulltext'));
        }
        if (! self::hasIndex('topics', 'topics_title_fulltext')) {
            Schema::table('topics', fn (Blueprint $t) => $t->fullText('title', 'topics_title_fulltext'));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (self::hasIndex('posts', 'posts_body_fulltext')) {
            Schema::table('posts', fn (Blueprint $t) => $t->dropFullText('posts_body_fulltext'));
        }
        if (self::hasIndex('topics', 'topics_title_fulltext')) {
            Schema::table('topics', fn (Blueprint $t) => $t->dropFullText('topics_title_fulltext'));
        }
    }

    private static function hasIndex(string $table, string $index): bool
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
};
