<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit log of staff/admin actions (delete, ban, move, role
 * change, …). Deliberately FK-free with snapshotted name/label columns, so an
 * entry stays complete and readable even after the actor, target or affected
 * user is later deleted or anonymized.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();   // the staff member (null = system)
            $table->string('actor_name')->nullable();                      // snapshot, survives deletion
            $table->string('action', 64)->index();                         // e.g. post.delete, user.ban, topic.lock
            $table->string('target_type', 32)->nullable();                 // post|topic|user|ip|report|…
            $table->string('target_id', 64)->nullable();                   // string so IPs fit too
            $table->string('target_label')->nullable();                    // snapshot: excerpt / title / username / ip
            $table->unsignedBigInteger('subject_user_id')->nullable()->index(); // the affected member, for "actions against X"
            $table->text('reason')->nullable();                            // moderator-supplied reason
            $table->json('meta')->nullable();                              // extra structured context (old/new, flags)
            $table->string('ip', 45)->nullable();                          // actor IP at the time
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
