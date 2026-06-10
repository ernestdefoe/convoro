<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gm_groups')) {
            Schema::create('gm_groups', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gm_group_user')) {
            Schema::create('gm_group_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('gm_groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['group_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('gm_messages')) {
            Schema::create('gm_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('gm_groups')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();
                $table->index(['group_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gm_messages');
        Schema::dropIfExists('gm_group_user');
        Schema::dropIfExists('gm_groups');
    }
};
