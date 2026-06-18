<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds recurring (subscription) support to the store: products can bill monthly,
 * and licenses track the Stripe subscription so the webhook can keep them in sync.
 * `listed` lets a product (e.g. the ConvoroCP panel) be purchasable without
 * appearing in the forum extensions grid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('recurring')->default(false);   // monthly subscription vs one-time
            $table->boolean('listed')->default(true);       // show in the extensions directory
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->timestamp('current_period_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['recurring', 'listed']);
        });
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['stripe_subscription_id', 'current_period_end']);
        });
    }
};
