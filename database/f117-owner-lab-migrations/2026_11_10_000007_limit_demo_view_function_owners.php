<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAST-PERF F1-117: laboratory-only owner cutover of read-only DEMO VIEWs.
 *
 * Production promotion prohibited. This is an executable model of the
 * least-privilege architecture, not a reviewed deployment migration.
 *
 * Postgres superuser view ownership is replaced with NOLOGIN/NOBYPASSRLS
 * role; the tenant-binding SECURITY DEFINER uses a separate NOLOGIN
 * owner which may SELECT only the binding registry.
 *
 * IMPORTANT: this remains only a lab model pending independent AppSec/DBA
 * inspection of RLS policy combinations, ownership, pooling and revocation.
 */
return new class extends Migration
{
    private const VIEW_OWNER = 'perf_f117_demo_view_owner';
    private const BINDING_OWNER = 'perf_f117_binding_definer_owner';
    private const SOURCE_TABLES = [
        'performance_ad_account_candidates',
        'performance_managed_campaigns',
        'performance_campaign_account_links',
        'performance_campaign_intents',
        'performance_intent_review_journal',
        'performance_demo_budget_scenarios',
    ];
    private const VIEWS = [
        'perf_f91_demo_account_read',
        'perf_f93_demo_campaign_account_read',
        'perf_f97_demo_review_read',
        'perf_f106_credential_budget_demo_read',
    ];

    public function up(): void
    {
        if (PHP_SAPI !== 'cli'
            || getenv('APP_ENV') !== 'testing'
            || getenv('PERF_F117_LIMITED_OWNER_LAB') !== '1'
            || getenv('PGHOST') !== '127.0.0.1'
            || getenv('PGPORT') !== '5432'
            || getenv('PGDATABASE') !== 'perf_test_campaign_registry'
            || DB::connection()->getDriverName() !== 'pgsql'
            || DB::scalar('SELECT current_database()') !== 'perf_test_campaign_registry'
            || DB::scalar('SELECT session_user') !== 'postgres'
            || (int) DB::scalar('SHOW server_version_num') < 160000
            || (int) DB::scalar('SHOW server_version_num') >= 170000) {
            throw new RuntimeException('F117 refuses any non-disposable, non-PG16 or non-admin execution');
        }

        $roleExists=DB::selectOne(
            'SELECT count(*) AS n FROM pg_catalog.pg_roles '
            .'WHERE rolname IN (?,?)',
            [self::VIEW_OWNER,self::BINDING_OWNER]
        );
        if ((int) $roleExists->n !== 0) {
            throw new RuntimeException('F117 requires fresh, unclaimed NOLOGIN owner role names');
        }

        foreach (self::VIEWS as $view) {
            $owner=DB::scalar(
                'SELECT pg_catalog.pg_get_userbyid(c.relowner) '
                .'FROM pg_catalog.pg_class c '
                .'JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace '
                .'WHERE n.nspname=? AND c.relname=? AND c.relkind=?',
                ['public',$view,'v']
            );
            if ($owner !== 'postgres') {
                throw new RuntimeException('F117 expected original superuser VIEW owner: '.$view);
            }
        }
        $helperOwner=DB::scalar(
            'SELECT pg_catalog.pg_get_userbyid(p.proowner) '
            .'FROM pg_catalog.pg_proc p '
            .'JOIN pg_catalog.pg_namespace n ON n.oid=p.pronamespace '
            .'WHERE n.nspname=? AND p.proname=?',
            ['public','perf_f91_tenant_for_login']
        );
        if ($helperOwner !== 'postgres') {
            throw new RuntimeException('F117 expected original F91 helper owner');
        }

        DB::statement(
            'CREATE ROLE '.self::VIEW_OWNER
            .' NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE '
            .'NOINHERIT NOBYPASSRLS NOREPLICATION'
        );
        DB::statement(
            'CREATE ROLE '.self::BINDING_OWNER
            .' NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE '
            .'NOINHERIT NOBYPASSRLS NOREPLICATION'
        );
        DB::statement(
            'GRANT USAGE ON SCHEMA public TO '
            .self::VIEW_OWNER.','.self::BINDING_OWNER
        );
        DB::statement(
            'GRANT SELECT ON TABLE public.performance_db_principal_tenant_bindings '
            .'TO '.self::BINDING_OWNER
        );
        DB::statement(
            'ALTER FUNCTION public.perf_f91_tenant_for_login() '
            .'OWNER TO '.self::BINDING_OWNER
        );
        DB::statement(
            'ALTER FUNCTION public.perf_f91_tenant_for_login() '
            .'SET search_path = pg_catalog'
        );
        DB::statement(
            'REVOKE ALL ON FUNCTION public.perf_f91_tenant_for_login() FROM PUBLIC'
        );
        DB::statement(
            'GRANT EXECUTE ON FUNCTION public.perf_f91_tenant_for_login() '
            .'TO '.self::VIEW_OWNER
        );

        foreach (self::SOURCE_TABLES as $table) {
            DB::statement(
                'GRANT SELECT ON TABLE public.'.$table.' TO '.self::VIEW_OWNER
            );

            // The old GUC-based permissive policies are still present in
            // this *lab*, so an additional permissive owner-only policy
            // allows principal-scoped read access while a RESTRICTIVE
            // policy ensures legacy GUC policy cannot widen the owner's
            // effective RLS scope. Neither policy grants INSERT/UPDATE.
            DB::statement(
                'CREATE POLICY perf_f117_owner_login_select ON public.'.$table
                .' AS PERMISSIVE FOR SELECT TO '.self::VIEW_OWNER
                .' USING (tenant_id = public.perf_f91_tenant_for_login())'
            );
            DB::statement(
                'CREATE POLICY perf_f117_owner_login_fence ON public.'.$table
                .' AS RESTRICTIVE FOR SELECT TO '.self::VIEW_OWNER
                .' USING (tenant_id = public.perf_f91_tenant_for_login())'
            );
        }

        foreach (self::VIEWS as $view) {
            DB::statement(
                'ALTER VIEW public.'.$view.' OWNER TO '.self::VIEW_OWNER
            );
            DB::statement(
                'ALTER VIEW public.'.$view.' SET (security_barrier = true)'
            );
            DB::statement(
                'REVOKE ALL ON TABLE public.'.$view.' FROM PUBLIC'
            );
        }
    }

    public function down(): void
    {
        // Restoring elevated ownership and tenant-spoofable access as an
        // automatic rollback would be a security downgrade. Any eventual
        // production recovery MUST be reviewed by an independent DBA.
        throw new RuntimeException('F117 refuses automatic security downgrade or superuser ownership restoration');
    }
};
