<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'source')) {
                $table->string('source')->default('manual');     // manual | github
            }
            if (! Schema::hasColumn('products', 'repo')) {
                $table->string('repo')->nullable();               // owner/name on GitHub
            }
            if (! Schema::hasColumn('products', 'download_url')) {
                $table->string('download_url')->nullable();        // resolved archive URL (github)
            }
            if (! Schema::hasColumn('products', 'ref')) {
                $table->string('ref')->nullable();                 // tag/branch the download points at
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['source', 'repo', 'download_url', 'ref']);
        });
    }
};
