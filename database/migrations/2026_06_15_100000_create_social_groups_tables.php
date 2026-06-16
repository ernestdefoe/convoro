<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('color', 9)->default('#5B5BD6');
            $table->string('icon', 60)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('membership_type', 20)->default('open'); // open | approval | invite
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('topic_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index(['is_featured', 'member_count']);
        });

        Schema::create('social_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_group_id')->constrained('social_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // owner | moderator | member
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->timestamps();
            $table->unique(['social_group_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('social_group_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_group_id')->constrained('social_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->text('message')->nullable();
            $table->timestamps();
            $table->unique(['social_group_id', 'user_id']);
        });

        // 1:1 companion table for a member's chosen "primary" group (the badge
        // shown on their profile). Kept off the users table so it's opt-in and
        // cleanly removable.
        Schema::create('social_group_primary', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->foreignId('social_group_id')->nullable()->constrained('social_groups')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_group_primary');
        Schema::dropIfExists('social_group_join_requests');
        Schema::dropIfExists('social_group_members');
        Schema::dropIfExists('social_groups');
    }
};
