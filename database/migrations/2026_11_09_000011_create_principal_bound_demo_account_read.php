<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAST-PERF F1-91: tenant-scoped, read-only DEMO account view using the
 * database-authenticated SESSION_USER, not caller-supplied tenant GUC.
 *
 * Pilot only: a production SaaS needs separately reviewed IAM, connection
 * pooling, credential rotation and ownership/grant hardening.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F91 credential-bound read path requires PostgreSQL');
        }

        Schema::create('performance_db_principal_tenant_bindings', function (Blueprint $table): void {
            $table->string('db_login', 63)->primary();
            $table->uuid('tenant_id');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->foreign('tenant_id', 'perf_f91_binding_tenant_fk')
                ->references('id')->on('performance_tenants')->restrictOnDelete();
        });
        DB::statement(
            "ALTER TABLE performance_db_principal_tenant_bindings ADD CONSTRAINT "
            . "perf_f91_db_login_ck CHECK (db_login ~ '^[a-z_][a-z0-9_]{2,62}$')"
        );
        DB::statement('REVOKE ALL ON performance_db_principal_tenant_bindings FROM PUBLIC');

        DB::unprepared(<<<'SQL'
CREATE FUNCTION public.perf_f91_tenant_for_login()
RETURNS uuid
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog
AS $f91$
    SELECT b.tenant_id
    FROM public.performance_db_principal_tenant_bindings AS b
    WHERE b.db_login = session_user::text
      AND b.revoked_at IS NULL
    LIMIT 1
$f91$
SQL);
        DB::statement('REVOKE ALL ON FUNCTION public.perf_f91_tenant_for_login() FROM PUBLIC');

        // Narrow view, not a provider account lookup or a generic operational
        // authorization. The security barrier prevents unsafe planner pushdown.
        // The owner bypasses base-table RLS: the explicit login-bound predicate
        // is mandatory, and this view needs independent ownership review.
        DB::unprepared(<<<'SQL'
CREATE VIEW public.perf_f91_demo_account_read
WITH (security_barrier = true)
AS
SELECT a.tenant_id,
       a.portal_service_ref,
       a.channel,
       a.account_ref,
       a.connection_state
FROM public.performance_ad_account_candidates AS a
WHERE a.tenant_id = public.perf_f91_tenant_for_login()
  AND a.connection_state = 'unverified_demo'
  AND a.provider_connected = false
  AND a.dispatch_allowed = false
  AND a.billable = false
SQL);
        DB::statement('REVOKE ALL ON TABLE public.perf_f91_demo_account_read FROM PUBLIC');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('F91 rollback requires PostgreSQL');
        }
        DB::statement('DROP VIEW IF EXISTS public.perf_f91_demo_account_read');
        DB::statement('DROP FUNCTION IF EXISTS public.perf_f91_tenant_for_login()');
        Schema::dropIfExists('performance_db_principal_tenant_bindings');
    }
};
