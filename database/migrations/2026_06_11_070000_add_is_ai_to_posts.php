<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Flags posts authored by the AI assistant (e.g. auto-answers) for badging. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'is_ai')) {
                $table->boolean('is_ai')->default(false)->after('is_first');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'is_ai')) {
                $table->dropColumn('is_ai');
            }
        });
    }
};
