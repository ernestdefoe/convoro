<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // off | daily | weekly
            $table->string('digest_frequency')->default('weekly')->after('email');
            $table->timestamp('last_digest_at')->nullable()->after('digest_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_frequency', 'last_digest_at']);
        });
    }
};
