<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_followers', function (Blueprint $table) {
            $table->id();
            $table->string('actor')->unique();        // the remote actor URI (the follower)
            $table->string('inbox');                  // where we deliver activities
            $table->string('shared_inbox')->nullable(); // optional sharedInbox for fan-out
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_followers');
    }
};
