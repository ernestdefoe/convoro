<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Authoritative knowledge the AI grounds answers in (docs, changelog and,
        // later, curated KB articles) — embedded alongside but separate from the
        // post index so "Ask" can cite real software facts, not just forum chatter.
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);             // doc | changelog | kb
            $table->string('ref');                  // stable key within a kind
            $table->string('title');
            $table->string('url')->nullable();      // e.g. /docs/backups, /changelog
            $table->text('snippet');                // the citable / context text
            $table->integer('dims')->default(0);
            $table->longText('vector')->nullable(); // JSON-encoded float array
            $table->timestamps();
            $table->unique(['kind', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};
