<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events v4 — recurring events. `recurrence` is none|daily|weekly|biweekly|monthly,
 * optionally ending at `recurrence_until`. The reminder pass tracks which
 * occurrence it last notified for (per window) so each occurrence reminds once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }
        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'recurrence')) {
                $table->string('recurrence')->default('none')->after('ends_at');
            }
            if (! Schema::hasColumn('events', 'recurrence_until')) {
                $table->timestamp('recurrence_until')->nullable()->after('recurrence');
            }
            if (! Schema::hasColumn('events', 'reminded_day_for')) {
                $table->timestamp('reminded_day_for')->nullable();
            }
            if (! Schema::hasColumn('events', 'reminded_hour_for')) {
                $table->timestamp('reminded_hour_for')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['recurrence', 'recurrence_until', 'reminded_day_for', 'reminded_hour_for']);
        });
    }
};
