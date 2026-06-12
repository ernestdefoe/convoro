<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cross-references: a post in one topic links to another topic. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_references', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_post_id')->index();
            $table->unsignedBigInteger('source_topic_id');
            $table->unsignedBigInteger('target_topic_id')->index();
            $table->timestamps();
            $table->unique(['source_post_id', 'target_topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_references');
    }
};
