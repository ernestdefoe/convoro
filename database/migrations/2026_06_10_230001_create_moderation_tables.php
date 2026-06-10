<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IP logging on content + accounts (visible to staff for moderation).
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'registration_ip')) {
                $table->string('registration_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('users', 'last_ip')) {
                $table->string('last_ip', 45)->nullable();
            }
            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->string('ban_reason')->nullable();
            }
        });

        // Report / flag queue (posts or users).
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('reportable_type');           // post | user
            $table->unsignedBigInteger('reportable_id');
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('status')->default('open');    // open | resolved | dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['reportable_type', 'reportable_id']);
            $table->index('status');
        });

        // Banned IP addresses (blocked at the middleware layer).
        Schema::create('ip_bans', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_bans');
        Schema::dropIfExists('reports');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['registration_ip', 'last_ip', 'banned_at', 'ban_reason']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
