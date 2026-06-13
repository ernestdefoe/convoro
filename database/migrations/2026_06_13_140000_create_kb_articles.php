<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The editable source of truth for a curated KB article. Its body is
        // chunked into one-or-more embedded rows in `knowledge_sources`
        // (ref = "kba-{articleId}-{i}") so long articles retrieve by the
        // relevant passage and aren't capped to a single embedding.
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 250);
            $table->longText('body');
            $table->string('url')->nullable();
            $table->timestamps();
        });

        // Backfill: promote any existing single-row kb knowledge_sources into a
        // kb_article and relink that row as the article's chunk 0 (preserves its
        // embedding — no API call needed here).
        foreach (DB::table('knowledge_sources')->where('kind', 'kb')->get() as $r) {
            $id = DB::table('kb_articles')->insertGetId([
                'title' => $r->title,
                'body' => $r->snippet,
                'url' => $r->url,
                'created_at' => $r->created_at ?? now(),
                'updated_at' => $r->updated_at ?? now(),
            ]);
            DB::table('knowledge_sources')->where('id', $r->id)->update(['ref' => 'kba-'.$id.'-0']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
