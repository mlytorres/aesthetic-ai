<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defense-in-depth tenant isolation for affiliate tables via PostgreSQL Row-Level Security.
 *
 * Application-layer scoping (App\Concerns\HasTenantScope) is the primary enforcement path;
 * RLS is the safety net for bugs that accidentally use `withoutGlobalScope` or bypass Eloquent.
 *
 * Activation:
 *   The policy relies on a session-level `app.current_tenant_id` GUC that is set by
 *   App\Services\TenantContext::set() whenever a tenant is resolved. Queries issued outside
 *   a tenant context (seeders, admin-wide reads, CLI) see an empty result — callers that
 *   legitimately need cross-tenant access (admin audit, platform stats) must either:
 *     (a) run `SET LOCAL app.current_tenant_id = 'all'` which the policy explicitly allows, or
 *     (b) connect as a role with BYPASSRLS (production DB admin role).
 *
 * Driver guard:
 *   RLS is a Postgres feature. Tests run on SQLite and local dev may too — this migration
 *   is a no-op for any connection other than pgsql so the test suite stays green.
 */
return new class extends Migration
{
    private const TABLES = [
        'affiliate_partners',
        'affiliate_campaigns',
        'campaign_assets',
        'affiliate_links',
        'attribution_events',
        'affiliate_payout_ledgers',
        'affiliate_terms_acceptances',
    ];

    public function up(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

            // Policy: allow row access when the session GUC matches the row's tenant_id,
            // or when the GUC is explicitly set to the sentinel value 'all' (admin context).
            // Split into two statements — pgsql rejects multiple commands in a prepared statement.
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING (
                        current_setting('app.current_tenant_id', true) = 'all'
                        OR tenant_id::text = current_setting('app.current_tenant_id', true)
                    )
                    WITH CHECK (
                        current_setting('app.current_tenant_id', true) = 'all'
                        OR tenant_id::text = current_setting('app.current_tenant_id', true)
                    )
            SQL);
        }
    }

    public function down(): void
    {
        if (! $this->isPostgres()) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
