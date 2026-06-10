<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            Schema::create('events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');         // organizer
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('url')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->index('starts_at');
            });
        }

        if (! Schema::hasTable('event_rsvps')) {
            Schema::create('event_rsvps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('user_id');
                $table->string('status')->default('going');     // going | maybe
                $table->timestamp('created_at')->nullable();
                $table->unique(['event_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rsvps');
        Schema::dropIfExists('events');
    }
};
