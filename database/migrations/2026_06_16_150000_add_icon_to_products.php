<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'icon')) {
            Schema::table('products', function (Blueprint $table) {
                // Inline SVG markup for the product's icon, so the detail page can
                // show a real glyph next to the name even when the extension isn't
                // installed on this host. Populated from the repo on GitHub sync.
                $table->longText('icon')->nullable()->after('image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'icon')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
