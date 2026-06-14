<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events v2 — richer events: online/in-person flag, an optional capacity, and
 * per-event reminder bookkeeping so the scheduled reminder pass only fires once
 * per window. Idempotent so it's safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('location');
            }
            if (! Schema::hasColumn('events', 'capacity')) {
                $table->unsignedInteger('capacity')->nullable()->after('is_online');
            }
            if (! Schema::hasColumn('events', 'reminded_day')) {
                $table->boolean('reminded_day')->default(false);
            }
            if (! Schema::hasColumn('events', 'reminded_hour')) {
                $table->boolean('reminded_hour')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'capacity', 'reminded_day', 'reminded_hour']);
        });
    }
};
