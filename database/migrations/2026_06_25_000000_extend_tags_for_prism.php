<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Convoro 2 "Prism": promote tags from flat labels to the primary, hero-bearing
// organization. Adds sub-tag nesting (parent_id), an icon + blurb for the hero,
// ordering, and a flexible `hero` JSON for full per-tag customization (image,
// palette, icon overrides).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id')->index();
            $table->string('icon')->nullable()->after('color');
            $table->string('description', 500)->nullable()->after('icon');
            $table->unsignedInteger('position')->default(0)->after('description');
            $table->json('hero')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'icon', 'description', 'position', 'hero']);
        });
    }
};
