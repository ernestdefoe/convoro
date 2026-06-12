<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social Login — link OAuth identities to local accounts.
 *
 * Adds the three non-sensitive columns the extension needs to recognise a
 * returning social user. Idempotent (guards every column) so it is safe on
 * installs that briefly carried the feature in core. Access tokens are NEVER
 * stored on the user row — they live in Settings (e.g. github.token) — so any
 * legacy `github_token` column is dropped here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'oauth_provider')) {
                $table->string('oauth_provider')->nullable();
            }
            if (! Schema::hasColumn('users', 'oauth_id')) {
                $table->string('oauth_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'oauth_avatar')) {
                $table->string('oauth_avatar')->nullable();
            }
        });

        if (Schema::hasColumn('users', 'github_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('github_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['oauth_provider', 'oauth_id', 'oauth_avatar'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
