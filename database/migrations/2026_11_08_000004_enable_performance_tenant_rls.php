<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Defense-in-depth ONLY. RLS is not a substitute for workload authentication,
 * application authorization, least-privilege SQL role, or tenant-bound queries.
 */
return new class extends Migration
{
    private const TABLES = [
        'performance_tenants' => 'id',
        'portal_service_links' => 'tenant_id',
        'operational_memberships' => 'tenant_id',
        'service_eligibility_snapshots' => 'tenant_id',
        'audit_events' => 'tenant_id',
        'idempotency_receipts' => 'tenant_id',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Tenant RLS requires PostgreSQL; no fallback is allowed.');
        }

        foreach (self::TABLES as $table => $column) {
            // Constant allowlisted identifiers. Never interpolate client input.
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
            $tenant = "NULLIF(current_setting('app.performance_tenant_id', true), '')::uuid";
            $predicate = "\"{$column}\" = {$tenant}";
            DB::statement(
                "CREATE POLICY perf_tenant_boundary ON \"{$table}\" "
                . "FOR ALL USING ({$predicate}) WITH CHECK ({$predicate})"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Tenant RLS removal requires PostgreSQL.');
        }

        foreach (array_reverse(array_keys(self::TABLES)) as $table) {
            DB::statement("DROP POLICY IF EXISTS perf_tenant_boundary ON \"{$table}\"");
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }
    }
};
