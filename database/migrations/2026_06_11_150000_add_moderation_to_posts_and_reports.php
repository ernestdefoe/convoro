<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Held by the AI moderation copilot pending human review (hidden from public).
            $table->boolean('hidden')->default(false)->index()->after('detected_locale');
            $table->json('moderation')->nullable()->after('hidden');
        });

        Schema::table('reports', function (Blueprint $table) {
            // 'user' (member-filed) or 'ai' (moderation copilot).
            $table->string('source', 12)->default('user')->after('reason');
            $table->json('ai_meta')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['hidden', 'moderation']);
        });
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['source', 'ai_meta']);
        });
    }
};
