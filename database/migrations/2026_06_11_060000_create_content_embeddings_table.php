<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semantic search index for "Ask Convoro": one embedding vector per post,
 * with the bits needed to build an answer's context + citations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->unique();
            $table->unsignedBigInteger('topic_id')->index();
            $table->string('title');
            $table->string('slug');
            $table->text('snippet');
            $table->unsignedSmallInteger('dims')->default(0);
            $table->longText('vector'); // JSON-encoded float array
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_embeddings');
    }
};
