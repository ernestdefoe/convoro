<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-user toggle for instant email notifications (replies, mentions, DMs, wall). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'notify_email')) {
                $table->boolean('notify_email')->default(true)->after('digest_frequency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'notify_email')) {
                $table->dropColumn('notify_email');
            }
        });
    }
};
