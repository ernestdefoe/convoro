<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POC — managed hosting. A "tenant" is one provisioned Convoro community. Lives
 * on the central (account-hub) database; each tenant's actual forum data lives
 * in its own isolated database (a separate sqlite file locally).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database');                              // path/name of the tenant's own DB
            $table->unsignedBigInteger('owner_user_id')->index();    // the central Convoro Account that owns it
            $table->string('status')->default('provisioning');       // provisioning | active | failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
