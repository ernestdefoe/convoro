<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Switch the search full-text indexes to MySQL's built-in `ngram` parser so
 * search works for languages without spaces between words (Chinese, Japanese,
 * Korean, Thai, …). The default parser tokenises on whitespace, so a CJK query
 * never matched anything — this is the zero-infrastructure, shared-hosting-
 * friendly path to multilingual search (no Typesense/Meilisearch daemon needed;
 * those remain an optional upgrade via config('convoro.search.driver')).
 *
 * A column can carry only one full-text index that MATCH() will use, so we drop
 * the word-parser indexes and recreate them WITH PARSER ngram. ngram tokenises
 * Latin text too, so English/European search keeps working. MySQL only —
 * sqlite/pgsql installs fall back to LIKE in the database search driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropIndex('posts', 'posts_body_fulltext');
        $this->dropIndex('topics', 'topics_title_fulltext');

        if (! $this->hasIndex('posts', 'posts_body_ngram')) {
            DB::statement('ALTER TABLE `posts` ADD FULLTEXT INDEX `posts_body_ngram` (`body_html`) WITH PARSER ngram');
        }
        if (! $this->hasIndex('topics', 'topics_title_ngram')) {
            DB::statement('ALTER TABLE `topics` ADD FULLTEXT INDEX `topics_title_ngram` (`title`) WITH PARSER ngram');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropIndex('posts', 'posts_body_ngram');
        $this->dropIndex('topics', 'topics_title_ngram');

        if (! $this->hasIndex('posts', 'posts_body_fulltext')) {
            DB::statement('ALTER TABLE `posts` ADD FULLTEXT INDEX `posts_body_fulltext` (`body_html`)');
        }
        if (! $this->hasIndex('topics', 'topics_title_fulltext')) {
            DB::statement('ALTER TABLE `topics` ADD FULLTEXT INDEX `topics_title_fulltext` (`title`)');
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if ($this->hasIndex($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function hasIndex(string $table, string $index): bool
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
