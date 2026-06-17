<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Convoro account to a Discord identity: the stable Discord user id
 * (used by the bot to look up the guild member for role sync), plus the display
 * username/avatar for the profile. No access token is stored — roles are read
 * through the bot, not the member's token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_id')->nullable()->unique()->after('github_login');
            $table->string('discord_username')->nullable()->after('discord_id');
            $table->string('discord_avatar')->nullable()->after('discord_username');
            $table->timestamp('discord_synced_at')->nullable()->after('discord_avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['discord_id', 'discord_username', 'discord_avatar', 'discord_synced_at']);
        });
    }
};
