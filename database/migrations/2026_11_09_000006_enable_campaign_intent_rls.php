<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAST-PERF F1-22: per-tenant FORCE RLS on the new campaign tables.
 * Future operational identity-to-GUC binding is NOT authorized.
 */
return new class extends Migration
{
    private const TABLES = [
        'performance_managed_campaigns',
        'performance_campaign_intents',
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-22 RLS requires PostgreSQL.');
        }
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE \"{$table}\" FORCE ROW LEVEL SECURITY");
            $predicate = "\"tenant_id\" = NULLIF("
                . "current_setting('app.performance_tenant_id', true), '')::uuid";
            DB::statement(
                "CREATE POLICY perf_campaign_tenant_boundary ON \"{$table}\" "
                . "FOR ALL USING ({$predicate}) WITH CHECK ({$predicate})"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F1-22 RLS rollback requires PostgreSQL.');
        }
        foreach (array_reverse(self::TABLES) as $table) {
            DB::statement("DROP POLICY IF EXISTS perf_campaign_tenant_boundary ON \"{$table}\"");
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }
    }
};
