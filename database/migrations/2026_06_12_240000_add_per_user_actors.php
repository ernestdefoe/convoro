<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-user ActivityPub identity (Phase 3) — generated lazily when a
            // local member first federates. ap_username is their fediverse handle.
            $table->string('ap_username')->nullable()->unique();
            $table->text('ap_public_key')->nullable();
            $table->text('ap_private_key')->nullable();
        });
        Schema::table('federation_followers', function (Blueprint $table) {
            // null = follows the community actor; else follows that local user.
            $table->unsignedBigInteger('user_id')->nullable()->after('id')->index();
            $table->dropUnique(['actor']); // an actor may follow several local users + the community
            $table->unique(['user_id', 'actor']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ap_username', 'ap_public_key', 'ap_private_key']);
        });
        Schema::table('federation_followers', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'actor']);
            $table->dropColumn('user_id');
            $table->unique('actor');
        });
    }
};
