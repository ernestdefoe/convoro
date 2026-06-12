<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('detected_locale', 12)->nullable()->after('body_html');
        });

        // Reuse content_translations for DMs: add a nullable message_id alongside
        // the existing post_id (one of the two is set per row).
        Schema::table('content_translations', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->nullable()->change();
            $table->unsignedBigInteger('message_id')->nullable()->after('post_id')->index();
            $table->unique(['message_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::table('content_translations', function (Blueprint $table) {
            $table->dropUnique(['message_id', 'locale']);
            $table->dropColumn('message_id');
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('detected_locale');
        });
    }
};
