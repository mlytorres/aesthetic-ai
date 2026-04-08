<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tenant_id and role to the users table.
 * Users are clinic staff (coordinators, surgeons, admins, owners).
 * Super-admin users have tenant_id = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // nullable: super-admin accounts don't belong to a tenant
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->string('role', 32)
                ->default('coordinator')
                ->after('tenant_id');

            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropColumn(['tenant_id', 'role', 'deleted_at']);
        });
    }
};
