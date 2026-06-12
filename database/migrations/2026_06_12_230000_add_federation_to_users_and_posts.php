<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_federated')->default(false);
            $table->string('federated_actor')->nullable()->unique(); // remote actor URI
            $table->string('federated_handle')->nullable();          // @user@server for display
            $table->string('federated_inbox')->nullable();           // where to deliver replies
        });
        Schema::table('posts', function (Blueprint $table) {
            // Remote ActivityPub object id — dedupes inbound replies + supports Delete.
            $table->string('federated_object')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_federated', 'federated_actor', 'federated_handle', 'federated_inbox']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('federated_object');
        });
    }
};
