<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favorite Team — stores each member's chosen FBS team as its ESPN team id on
 * the users row. Nullable string (ids are numeric strings like "333"); no
 * foreign key since the team catalog lives in the extension's teams.json, not
 * the database. Present::avatar reads $user->favorite_team into the avatar
 * payload, which the forum badge strip resolves to a logo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'favorite_team')) {
                $table->string('favorite_team', 16)->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'favorite_team')) {
                $table->dropColumn('favorite_team');
            }
        });
    }
};
