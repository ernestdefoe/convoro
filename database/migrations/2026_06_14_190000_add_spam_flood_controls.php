<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spam, flood & abuse controls.
 *
 * - topics.hidden: a topic held for moderator approval (e.g. a new member's
 *   first topic, or one tripped by the banned-words filter). Hidden from every
 *   public listing; only its author + moderators can see it until approved.
 * - categories.slow_mode: per-category cooldown in seconds between a member's
 *   posts in that category (0 = off).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            if (! Schema::hasColumn('topics', 'hidden')) {
                $table->boolean('hidden')->default(false)->index();
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'slow_mode')) {
                $table->unsignedInteger('slow_mode')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            if (Schema::hasColumn('topics', 'hidden')) {
                $table->dropColumn('hidden');
            }
        });
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'slow_mode')) {
                $table->dropColumn('slow_mode');
            }
        });
    }
};
