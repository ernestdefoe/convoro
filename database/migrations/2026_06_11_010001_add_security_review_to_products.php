<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // AI security review results (unbiased, run against the fetched source).
            if (! Schema::hasColumn('products', 'review_rating')) {
                $table->string('review_rating')->nullable();   // safe | caution | unsafe | error
                $table->unsignedTinyInteger('review_score')->nullable(); // 0-100
                $table->text('review_summary')->nullable();
                $table->json('review_findings')->nullable();    // [{severity,category,title,detail}]
                $table->string('review_model')->nullable();
                $table->string('reviewed_version')->nullable();
                $table->string('review_status')->default('none'); // none|queued|reviewing|done|error
                $table->timestamp('reviewed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'review_rating', 'review_score', 'review_summary', 'review_findings',
                'review_model', 'reviewed_version', 'review_status', 'reviewed_at',
            ]);
        });
    }
};
