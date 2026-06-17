<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dedupe + audit of topic → Discord webhook announcements (one row per topic). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id')->unique();
            $table->string('status')->default('pending'); // posted | skipped | failed
            $table->string('error', 500)->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_posts');
    }
};
