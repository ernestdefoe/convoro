<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'trust_level')) {
                $table->unsignedTinyInteger('trust_level')->default(0)->index();
            }
            if (! Schema::hasColumn('users', 'trust_locked')) {
                // True once an admin pins the level — auto-evaluation then skips them.
                $table->boolean('trust_locked')->default(false);
            }
            if (! Schema::hasColumn('users', 'trust_promoted_at')) {
                $table->timestamp('trust_promoted_at')->nullable();
            }
        });

        // Existing members shouldn't be retroactively treated as brand-new
        // (and suddenly have links stripped), so seed them at Member (1). Only
        // accounts created after this migration start at New (0).
        DB::table('users')->where('trust_level', 0)->update(['trust_level' => 1]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['trust_level', 'trust_locked', 'trust_promoted_at'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
