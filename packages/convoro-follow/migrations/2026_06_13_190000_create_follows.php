<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('follows')) {
            return;
        }
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follower_id')->index();   // who is following
            $table->unsignedBigInteger('followee_id')->index();   // who is being followed
            $table->timestamp('created_at')->nullable();
            $table->unique(['follower_id', 'followee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
