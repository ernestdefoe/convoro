<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            // Group discussions ARE topics. A null group_id means a normal forum
            // topic; a set group_id scopes the topic to a social group, where a
            // global scope hides it from every public listing (feed, search,
            // profile, RSS, sitemap, digest). Cascades on group delete so a
            // removed group never orphans its discussions into the main feed.
            $table->foreignId('group_id')->nullable()->after('category_id')
                ->constrained('social_groups')->cascadeOnDelete();
            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
