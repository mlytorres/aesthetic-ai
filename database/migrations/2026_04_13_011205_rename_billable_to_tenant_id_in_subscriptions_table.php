<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fresh installs already create subscriptions.tenant_id (the column was
        // fixed in the create migration). This migration only runs for existing
        // databases that were created with the original billable_id/billable_type scheme.
        if (! Schema::hasColumn('subscriptions', 'billable_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->renameColumn('billable_id', 'tenant_id');
            $table->dropColumn('billable_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('subscriptions', 'tenant_id')) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->renameColumn('tenant_id', 'billable_id');
            $table->string('billable_type')->nullable();
        });
    }
};
