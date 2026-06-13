<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'is_qa')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('is_qa')->default(false);
            });
        }
        if (! Schema::hasColumn('topics', 'solved_post_id')) {
            Schema::table('topics', function (Blueprint $table) {
                $table->unsignedBigInteger('solved_post_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Drop the index first (SQLite rebuilds the table on column drop and
        // chokes on a dangling index otherwise).
        if (Schema::hasColumn('topics', 'solved_post_id')) {
            Schema::table('topics', function (Blueprint $table) {
                $table->dropIndex(['solved_post_id']);
                $table->dropColumn('solved_post_id');
            });
        }
        if (Schema::hasColumn('categories', 'is_qa')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('is_qa');
            });
        }
    }
};
