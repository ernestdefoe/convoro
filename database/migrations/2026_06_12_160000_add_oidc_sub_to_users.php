<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stable subject id from the OIDC provider, so logins survive email changes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'oidc_sub')) {
                $table->string('oidc_sub')->nullable()->unique()->after('invited_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'oidc_sub')) {
                $table->dropColumn('oidc_sub');
            }
        });
    }
};
