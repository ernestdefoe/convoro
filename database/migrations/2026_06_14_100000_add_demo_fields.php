<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POC — on-demand demos. Reuses the `tenants` registry: a demo is a tenant with
 * kind='demo', a requester email, and a 3-day expiry. Plus a waitlist for when
 * all demo slots are full.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('kind')->default('tenant')->index();   // tenant | demo
            $table->string('email')->nullable();                  // demo requester
            $table->timestamp('expires_at')->nullable()->index(); // demos self-destruct after this
            $table->timestamp('reminded_at')->nullable();         // 24h-before-expiry email sent
        });

        Schema::create('demo_waitlist', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_waitlist');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['kind', 'email', 'expires_at', 'reminded_at']);
        });
    }
};
