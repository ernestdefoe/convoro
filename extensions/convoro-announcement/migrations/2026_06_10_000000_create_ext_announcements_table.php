<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ext_announcements')) {
            return;
        }

        Schema::create('ext_announcements', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        DB::table('ext_announcements')->insert([
            'body' => 'Welcome to Convoro — this banner is served by a real, installable extension. 🎉',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_announcements');
    }
};
