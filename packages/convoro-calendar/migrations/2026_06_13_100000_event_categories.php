<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events v3 — optional event categories (admin-managed, coloured) that events
 * can belong to. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_categories')) {
            Schema::create('event_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('color')->default('#5b5bd6');
                $table->unsignedInteger('position')->default(0);
                $table->timestamp('created_at')->nullable();
            });
        }

        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'category_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'category_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }
        Schema::dropIfExists('event_categories');
    }
};
