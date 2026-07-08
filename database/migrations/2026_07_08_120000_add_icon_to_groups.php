<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give user groups a Font Awesome icon so the staff badge can render as a small
 * inline glyph next to the username (like Flarum's group badges) instead of a
 * text pill under the avatar. Seeds sensible defaults for the system groups;
 * Present::staffBadge falls back to a generic icon when a staff group has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (! Schema::hasColumn('groups', 'icon')) {
                $table->string('icon', 60)->nullable()->after('color');
            }
        });

        $icons = [
            'admin' => 'fa-solid fa-shield-halved',
            'moderator' => 'fa-solid fa-user-shield',
        ];
        foreach ($icons as $key => $icon) {
            DB::table('groups')->where('key', $key)->whereNull('icon')->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            if (Schema::hasColumn('groups', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
