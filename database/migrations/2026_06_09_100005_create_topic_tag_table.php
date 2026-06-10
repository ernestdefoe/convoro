<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('topic_tag', function (Blueprint $table) {
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['topic_id', 'tag_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('topic_tag'); }
};
