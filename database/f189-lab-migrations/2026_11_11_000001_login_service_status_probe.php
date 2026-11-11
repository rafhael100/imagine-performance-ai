<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F189: an explicit, login-scoped service-status probe.
 * Local CI status is NOT authoritative Portal service entitlement.
 */
return new class extends Migration {
    public function up():void {
        if(PHP_SAPI!=='cli' || getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F189_SERVICE_STATUS_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'){
            throw new RuntimeException('F189 is a disposable PG16 migration only');
        }
        DB::unprepared(<<<'SQL'
CREATE FUNCTION public.perf_f189_login_service_status()
RETURNS TABLE(
    tenant_id uuid,
    tenant_status text,
    tenant_revoked_at timestamptz,
    link_status text,
    link_revision text,
    eligibility_state text,
    eligibility_source text,
    eligibility_revision text,
    eligibility_observed_at timestamptz
)
LANGUAGE sql STABLE SECURITY DEFINER
SET search_path = pg_catalog
AS $$
    SELECT t.id,
           t.status::text,
           t.revoked_at,
           l.link_status::text,
           l.source_revision::text,
           s.state::text,
           s.source::text,
           s.source_revision::text,
           s.observed_at
    FROM public.performance_tenants t
    LEFT JOIN public.portal_service_links l
      ON l.tenant_id=t.id AND l.portal_service_ref='svc-demo-f88'
    LEFT JOIN LATERAL (
        SELECT e.state,e.source,e.source_revision,e.observed_at
        FROM public.service_eligibility_snapshots e
        WHERE e.tenant_id=t.id
          AND e.portal_service_ref='svc-demo-f88'
        ORDER BY e.observed_at DESC,e.id DESC
        LIMIT 1
    ) AS s ON true
    WHERE current_database()='perf_test_campaign_registry'
      AND session_user::text IN ('perf_f91_reader_a','perf_f91_reader_b')
      AND t.id=public.perf_f91_tenant_for_login()
$$;
REVOKE ALL ON FUNCTION public.perf_f189_login_service_status() FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.perf_f189_login_service_status()
    TO perf_f91_reader_a,perf_f91_reader_b;
SQL);
        if((int)DB::scalar(
            "SELECT count(*) FROM pg_proc WHERE proname='perf_f189_login_service_status'
             AND prosecdef=true"
        )!==1){
            throw new RuntimeException('F189 only permitted through scoped function');
        }
    }

    public function down():void {
        throw new RuntimeException('F189 refuses silent downgrade of service-status fence');
    }
};
