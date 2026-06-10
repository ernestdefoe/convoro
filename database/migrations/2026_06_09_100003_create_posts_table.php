<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('body_html');               // sanitized TipTap HTML (render)
            $table->longText('body_json')->nullable();     // ProseMirror JSON (re-edit)
            $table->boolean('is_first')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['topic_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('posts'); }
};
