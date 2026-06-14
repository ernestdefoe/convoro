<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managed-hosting: per-tenant billing summary, custom-domain mapping, and a
 * time-series metrics table powering the client dashboard's resource graphs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Plan / billing summary (mirrors Stripe; updated from webhooks in prod).
            $table->string('plan')->default('starter');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('billing_interval')->default('month');     // month | year
            $table->string('billing_status')->default('active');      // active | past_due | canceled | trialing
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();      // = next payment date
            $table->string('card_brand')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();

            // Plan resource allowances (for usage-vs-quota bars).
            $table->unsignedBigInteger('quota_storage_bytes')->default(5 * 1024 * 1024 * 1024);  // 5 GB
            $table->unsignedInteger('quota_members')->default(1000);
            $table->unsignedInteger('quota_bandwidth_gb')->default(100);

            // Custom domain mapping.
            $table->string('custom_domain')->nullable()->unique();
            $table->timestamp('custom_domain_verified_at')->nullable();
            $table->string('cert_status')->default('none');           // none | pending | active
        });

        Schema::create('tenant_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at')->index();
            $table->unsignedBigInteger('db_bytes')->default(0);
            $table->unsignedBigInteger('redis_bytes')->default(0);
            $table->unsignedBigInteger('storage_bytes')->default(0);  // uploads on disk
            $table->unsignedInteger('jobs_processed')->default(0);    // since last sample
            $table->unsignedInteger('queue_depth')->default(0);
            $table->unsignedInteger('requests')->default(0);          // since last sample
            $table->unsignedBigInteger('bandwidth_bytes')->default(0);
            $table->float('cpu_pct')->default(0);
            $table->unsignedInteger('avg_response_ms')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_metrics');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'plan', 'price_cents', 'billing_interval', 'billing_status',
                'current_period_start', 'current_period_end', 'card_brand', 'card_last4',
                'stripe_customer_id', 'stripe_subscription_id',
                'quota_storage_bytes', 'quota_members', 'quota_bandwidth_gb',
                'custom_domain', 'custom_domain_verified_at', 'cert_status',
            ]);
        });
    }
};
