<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type')->default('extension');   // extension | theme
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('package')->nullable();           // composer/extension id this license unlocks
            $table->string('version')->default('1.0.0');
            $table->unsignedInteger('price_cents')->default(0); // in the store currency
            $table->string('currency', 3)->default('usd');
            $table->string('image')->nullable();
            $table->string('download_path')->nullable();      // storage path to the release zip
            $table->boolean('published')->default(true);
            $table->boolean('featured')->default(false);
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('status')->default('active');      // active | refunded | revoked
            $table->string('stripe_session_id')->nullable();
            $table->string('stripe_payment_intent')->nullable();
            $table->unsignedInteger('activations')->default(0);
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('products');
    }
};
