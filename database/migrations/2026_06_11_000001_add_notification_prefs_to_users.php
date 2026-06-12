<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-type notification opt-outs, e.g. {"reaction": false}.
            // Null / missing key = enabled (opt-out model). Stored as JSON
            // text for cross-driver portability (MySQL/MariaDB/Postgres/SQLite).
            $table->json('notification_prefs')->nullable()->after('last_digest_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_prefs');
        });
    }
};
