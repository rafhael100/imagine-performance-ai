<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F1-112: VERSIONED REVOCATION MIGRATION for disposable CI PostgreSQL16.
 *
 * DO NOT promote into default migration set, staging, VPS, or production.
 * Unlike F99's ad-hoc CI test, this migration records a durable, reviewed
 * plan for closure of the F22-F28/F88-F90 direct SQL privileges. Applying
 * it in a real environment requires a separate role inventory, IAM cutover,
 * DBA/AppSec approval, maintenance plan and validation of every consumer.
 */
return new class extends Migration
{
    private const ROLES = [
        'perf_campaign_test_user',
        'perf_f23_writer',
        'perf_f24_reader',
        'perf_f27_journal_reader',
        'perf_f28_app_reader',
        'perf_f88_registrar',
        'perf_f89_link_registrar',
        'perf_f90_projection_reader',
    ];

    private const TABLES = [
        'performance_tenants',
        'portal_service_links',
        'operational_memberships',
        'service_eligibility_snapshots',
        'audit_events',
        'idempotency_receipts',
        'performance_managed_campaigns',
        'performance_campaign_intents',
        'performance_intent_review_journal',
        'performance_ad_account_candidates',
        'performance_campaign_account_links',
        'performance_db_principal_tenant_bindings',
        'performance_demo_budget_scenarios',
    ];

    public function up(): void
    {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F112_LEGACY_GRANT_MIGRATION_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry'
            || DB::connection()->getDriverName() !== 'pgsql'
            || DB::scalar('SELECT current_database()') !== 'perf_test_campaign_registry'
            || DB::scalar('SELECT session_user') !== 'postgres') {
            throw new RuntimeException('F112 refuses non-ephemeral or non-admin SQL execution');
        }

        foreach (self::ROLES as $role) {
            $attributes=DB::selectOne(
                'SELECT rolsuper, rolcreaterole, rolcreatedb, rolbypassrls '
                .'FROM pg_catalog.pg_roles WHERE rolname=?', [$role]
            );
            if ($attributes === null || $attributes->rolsuper
                || $attributes->rolcreaterole || $attributes->rolcreatedb
                || $attributes->rolbypassrls) {
                throw new RuntimeException('F112 legacy role precondition failed: '.$role);
            }
        }
        foreach (self::TABLES as $table) {
            $exists=DB::scalar(
                "SELECT to_regclass(?) IS NOT NULL", ['public.'.$table]
            );
            if (!$exists) {
                throw new RuntimeException('F112 required table missing: '.$table);
            }
        }

        // Static allowlists; no role/table name is constructed from a user
        // request. Revoke both explicit legacy grants and PUBLIC grants.
        $principals=implode(',',self::ROLES).',PUBLIC';
        foreach (self::TABLES as $table) {
            DB::statement(
                'REVOKE ALL PRIVILEGES ON TABLE public.'.$table
                .' FROM '.$principals
            );
        }

        // Verify *effective* grants, including role inheritance, PUBLIC
        // and column-level SELECT/UPDATE/REFERENCES. PostgreSQL16 REVOKE
        // ALL PRIVILEGES ON TABLE removed direct column-level grants in CI,
        // but inherited permissions can survive direct-role REVOKE.
        // Refuse to record a completed migration if any capability survives.
        $privileges=['SELECT','INSERT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'];
        foreach (self::TABLES as $table) {
            $columns=DB::select(
                'SELECT attname FROM pg_catalog.pg_attribute '
                .'WHERE attrelid=?::regclass AND attnum>0 AND NOT attisdropped',
                ['public.'.$table]
            );
            foreach (self::ROLES as $role) {
                foreach ($privileges as $priv) {
                    if (DB::scalar(
                        'SELECT has_table_privilege(?,?,?)',
                        [$role,'public.'.$table,$priv]
                    )) {
                        throw new RuntimeException('F112 residual table grant: '.$role.'/'.$table.'/'.$priv);
                    }
                }
                foreach ($columns as $column) {
                    foreach (['SELECT','UPDATE','REFERENCES'] as $priv) {
                        if (DB::scalar(
                            'SELECT has_column_privilege(?,?,?,?)',
                            [$role,'public.'.$table,$column->attname,$priv]
                        )) {
                            throw new RuntimeException(
                                'F112 residual column grant: '
                                .$role.'/'.$table.'/'.$column->attname.'/'.$priv
                            );
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Restoring tenant-spoofable direct SQL grants on rollback would be
        // a security regression. A DBA must design any recovery separately.
        throw new RuntimeException('F112 security downgrade forbidden: do not restore legacy grants');
    }
};
