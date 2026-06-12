<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Pin a GitHub-sourced product to a specific release tag. NULL = track
            // the latest release (auto-refresh on new published releases).
            if (! Schema::hasColumn('products', 'pinned_version')) {
                $table->string('pinned_version')->nullable()->after('ref');
            }
            // Last time the registry synced this product from GitHub.
            if (! Schema::hasColumn('products', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('pinned_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pinned_version', 'last_synced_at']);
        });
    }
};
