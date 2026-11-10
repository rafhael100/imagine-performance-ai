<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAST-PERF F1-119: CI-only removal of legacy tenant RLS policies based on
 * mutable app.performance_tenant_id (caller-controlled session GUC).
 *
 * This is NOT a production/staging migration. It is a separate, versioned
 * end-of-CI cutover after F112/F117/F118 have completed successfully.
 * Only the login-bound F117 VIEW-owner SELECT policies are retained, and
 * the F104 synthetic terminal-review INSERT policy remains unaffected.
 */
return new class extends Migration
{
    private const LEGACY = [
        'performance_tenants' => 'perf_tenant_boundary',
        'portal_service_links' => 'perf_tenant_boundary',
        'operational_memberships' => 'perf_tenant_boundary',
        'service_eligibility_snapshots' => 'perf_tenant_boundary',
        'audit_events' => 'perf_tenant_boundary',
        'idempotency_receipts' => 'perf_tenant_boundary',
        'performance_managed_campaigns' => 'perf_campaign_tenant_boundary',
        'performance_campaign_intents' => 'perf_campaign_tenant_boundary',
        'performance_intent_review_journal' => 'perf_f27_journal_tenant_read',
        'performance_ad_account_candidates' => 'perf_f88_tenant_boundary',
        'performance_campaign_account_links' => 'perf_f89_tenant_boundary',
    ];

    private const OWNER_SOURCES = [
        'performance_ad_account_candidates',
        'performance_managed_campaigns',
        'performance_campaign_account_links',
        'performance_campaign_intents',
        'performance_intent_review_journal',
        'performance_demo_budget_scenarios',
    ];

    public function up():void
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F119_DROP_GUC_RLS_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||(int)DB::scalar('SHOW server_version_num')<160000
            ||(int)DB::scalar('SHOW server_version_num')>=170000){
            throw new RuntimeException('F119 legacy GUC cutover prohibited outside disposable PG16 CI');
        }

        $owner=DB::selectOne(
            'SELECT rolcanlogin,rolsuper,rolbypassrls '
            .'FROM pg_catalog.pg_roles WHERE rolname=?',
            ['perf_f117_demo_view_owner']
        );
        if(!$owner || $owner->rolcanlogin || $owner->rolsuper || $owner->rolbypassrls){
            throw new RuntimeException('F119 requires F117 NOLOGIN/non-superuser VIEW ownership');
        }
        foreach(self::OWNER_SOURCES as $table){
            $policies=DB::select(
                'SELECT polname FROM pg_catalog.pg_policy '
                .'WHERE polrelid=?::regclass '
                ."AND polname IN ('perf_f117_owner_login_select','perf_f117_owner_login_fence')",
                ['public.'.$table]
            );
            if(count($policies)!==2){
                throw new RuntimeException('F119 cannot remove GUC before owner-login policies: '.$table);
            }
        }
        foreach(self::LEGACY as $table=>$policy){
            $row=DB::selectOne(
                'SELECT pg_catalog.pg_get_expr(p.polqual,p.polrelid) AS predicate,'
                .'p.polname,p.polcmd '
                .'FROM pg_catalog.pg_policy p '
                .'WHERE p.polrelid=?::regclass AND p.polname=?',
                ['public.'.$table,$policy]
            );
            if($row===null
                ||!is_string($row->predicate)
                ||!str_contains($row->predicate,'app.performance_tenant_id')){
                throw new RuntimeException('F119 expected exact legacy GUC policy: '.$table.'/'.$policy);
            }
            $rel=DB::selectOne(
                'SELECT relrowsecurity,relforcerowsecurity FROM pg_catalog.pg_class '
                .'WHERE oid=?::regclass',
                ['public.'.$table]
            );
            if(!$rel || !$rel->relrowsecurity || !$rel->relforcerowsecurity){
                throw new RuntimeException('F119 requires FORCE RLS on legacy relation '.$table);
            }
        }

        // All 11 policy names and table names are constants; no request input.
        // The original permissive policies are no longer used by any approved
        // demo reader after F117, and their direct grants were revoked in F112.
        foreach(self::LEGACY as $table=>$policy){
            DB::statement('DROP POLICY '.$policy.' ON public.'.$table);
        }

        // Fail if a future legacy policy was added outside this inventory.
        // This prevents marking an incomplete cutover as successful.
        $remaining=DB::scalar(
            "SELECT count(*) FROM pg_catalog.pg_policy p "
            ."JOIN pg_catalog.pg_class c ON c.oid=p.polrelid "
            ."JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace "
            ."WHERE n.nspname='public' "
            ."AND (coalesce(pg_catalog.pg_get_expr(p.polqual,p.polrelid),'') "
            ."LIKE '%app.performance_tenant_id%' "
            ."OR coalesce(pg_catalog.pg_get_expr(p.polwithcheck,p.polrelid),'') "
            ."LIKE '%app.performance_tenant_id%')"
        );
        if((int)$remaining!==0){
            throw new RuntimeException('F119 residual unsafe legacy tenant GUC RLS policy');
        }
    }

    public function down():void
    {
        // Reintroducing caller-controlled tenant policies would recreate a
        // confirmed cross-tenant exposure under direct SQL access. No
        // automatic rollback restoring the insecure policies is allowed.
        throw new RuntimeException('F119 refuses automatic restoration of legacy GUC tenant policies');
    }
};
