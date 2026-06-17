<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-seller GitHub connection: stores the OAuth access token (encrypted via the
 * User model cast) and the GitHub login, so a seller can list private repos in
 * the extension directory under their own account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('github_token')->nullable()->after('remember_token');
            $table->string('github_login')->nullable()->after('github_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['github_token', 'github_login']);
        });
    }
};
