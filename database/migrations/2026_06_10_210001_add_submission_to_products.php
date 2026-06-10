<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'submitter_email')) {
                $table->string('submitter_email')->nullable();       // who submitted (for payouts/contact)
            }
            if (! Schema::hasColumn('products', 'status')) {
                $table->string('status')->default('approved');        // pending | approved | rejected
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['submitter_email', 'status']);
        });
    }
};
