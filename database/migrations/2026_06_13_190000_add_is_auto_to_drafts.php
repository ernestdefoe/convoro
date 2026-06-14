<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the single rolling "auto-saved" draft per user that the composer writes
 * in the background, so it stays separate from explicitly-saved drafts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->boolean('is_auto')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('drafts', function (Blueprint $table) {
            $table->dropColumn('is_auto');
        });
    }
};
