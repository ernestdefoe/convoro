<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Social Login — one account, many linked providers.
 *
 * Originally a member could link a single OAuth identity (the users.oauth_*
 * columns). This moves identities into a join table so one member can connect
 * GitHub AND Google AND Facebook AND X at once. The legacy users.oauth_*
 * columns are kept (oauth_avatar still drives the displayed avatar) and the
 * existing single identity is backfilled here so nobody is logged out.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('provider');         // github|google|facebook|x
                $table->string('provider_id');      // the id at that provider
                $table->string('avatar')->nullable();
                $table->timestamps();

                // A given remote identity maps to exactly one member.
                $table->unique(['provider', 'provider_id']);
                // A member links each provider at most once.
                $table->unique(['user_id', 'provider']);
                $table->index('user_id');
            });
        }

        // Backfill the single legacy identity into the join table (idempotent).
        if (Schema::hasColumn('users', 'oauth_provider')) {
            DB::table('users')
                ->whereNotNull('oauth_provider')
                ->whereNotNull('oauth_id')
                ->orderBy('id')
                ->select(['id', 'oauth_provider', 'oauth_id', 'oauth_avatar'])
                ->chunk(500, function ($users) {
                    foreach ($users as $u) {
                        $exists = DB::table('social_accounts')
                            ->where('provider', $u->oauth_provider)
                            ->where('provider_id', $u->oauth_id)
                            ->exists();
                        if (! $exists) {
                            DB::table('social_accounts')->insert([
                                'user_id' => $u->id,
                                'provider' => $u->oauth_provider,
                                'provider_id' => $u->oauth_id,
                                'avatar' => $u->oauth_avatar,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
