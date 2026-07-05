<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            // Sandbox tags are practice areas: topics in them stay fully
            // usable but never appear in Trending and are excluded from
            // community-wide and per-member counts.
            $table->boolean('is_sandbox')->default(false)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('is_sandbox');
        });
    }
};
