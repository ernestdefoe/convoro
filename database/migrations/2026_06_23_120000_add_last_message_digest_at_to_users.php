<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Watermark for the periodic direct-message email digest: only DMs that arrived
 * (and are still unread) since this timestamp are included in the next digest,
 * so a member is never emailed twice about the same message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_message_digest_at')->nullable()->after('last_digest_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_message_digest_at');
        });
    }
};
