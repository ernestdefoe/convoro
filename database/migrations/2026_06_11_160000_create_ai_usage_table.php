<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table may already exist from the retired AI Helper extension — in
        // that case just add the columns core needs (e.g. `feature`) if missing.
        if (Schema::hasTable('ai_usage')) {
            Schema::table('ai_usage', function (Blueprint $table) {
                if (! Schema::hasColumn('ai_usage', 'feature')) {
                    $table->string('feature', 32)->default('general')->index();
                }
            });

            return;
        }

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 32)->default('general')->index();
            $table->string('model')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
    }
};
