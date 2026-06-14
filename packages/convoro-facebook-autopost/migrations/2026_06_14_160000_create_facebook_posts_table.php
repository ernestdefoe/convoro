<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Records each topic shared to Facebook — dedupes + stores the FB post id. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id')->unique();
            $table->string('fb_post_id')->nullable();
            $table->string('status')->default('pending'); // pending | posted | failed | skipped
            $table->text('error')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_posts');
    }
};
