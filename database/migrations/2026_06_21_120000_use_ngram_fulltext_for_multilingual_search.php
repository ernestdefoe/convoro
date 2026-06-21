<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Search full-text indexes.
 *
 * On MySQL we use the built-in `ngram` parser so search works for languages
 * without spaces between words (Chinese, Japanese, Korean, Thai…). MariaDB has
 * no ngram parser, so there we keep the default word-parser full-text indexes —
 * real multilingual/typo-tolerant search on MariaDB is provided instead by the
 * optional convoro-typesense extension. sqlite/pgsql fall back to LIKE in the
 * database search driver, so this migration is a no-op for them.
 *
 * Written to be safe to re-run / recover: it only ever adds the index that's
 * appropriate for the running engine, guarded by existence checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->isMariaDb()) {
            // MariaDB: default-parser full-text indexes (no ngram). Recreate if a
            // prior run dropped them.
            $this->ensureFulltext('posts', 'posts_body_fulltext', 'body_html');
            $this->ensureFulltext('topics', 'topics_title_fulltext', 'title');

            return;
        }

        // MySQL: swap the default-parser indexes for ngram ones.
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
        $this->ensureFulltext('posts', 'posts_body_fulltext', 'body_html');
        $this->ensureFulltext('topics', 'topics_title_fulltext', 'title');
    }

    private function ensureFulltext(string $table, string $index, string $column): void
    {
        if (! $this->hasIndex($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$index}` (`{$column}`)");
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if ($this->hasIndex($table, $index)) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }

    private function isMariaDb(): bool
    {
        try {
            return stripos((string) (DB::selectOne('select version() as v')->v ?? ''), 'mariadb') !== false;
        } catch (\Throwable) {
            return false;
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
