<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging area for unpublished topics — saved drafts and scheduled posts. Kept
 * separate from `topics` so every existing query stays "published only"; a draft
 * becomes a real topic when the member publishes it (or its scheduled time hits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('body_html')->nullable();
            $table->longText('body_json')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->json('tags')->nullable();
            $table->string('cover')->nullable();
            $table->json('poll')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
