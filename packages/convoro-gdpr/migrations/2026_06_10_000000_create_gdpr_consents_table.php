<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gdpr_consents')) {
            return;
        }

        Schema::create('gdpr_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();   // null = guest
            $table->string('ip_address', 45)->nullable();
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_consents');
    }
};
