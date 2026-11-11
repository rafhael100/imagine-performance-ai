<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FAST-PERF F184 — CI-only fix for stale managed=false campaigns in
 * credential-bound F93/F97 views used by F100 and current Portal F62/F63.
 * No operational migration or deploy; existing owners and ACLs retained.
 */
return new class extends Migration
{
    public function up():void
    {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F184_MANAGED_VISIBILITY_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'
            ||(int)DB::scalar('SHOW server_version_num')<160000
            ||(int)DB::scalar('SHOW server_version_num')>=170000){
            throw new RuntimeException('F184 views migration is disposable PG16 CI only');
        }

        $ownerAcl=static function(string $view):array{
            $sql="SELECT c.relowner::regrole::text AS owner_name, "
                ."coalesce(c.relacl::text,'') AS acl "
                ."FROM pg_catalog.pg_class c "
                ."WHERE c.oid=to_regclass(?)";
            $rows=DB::select($sql,[$view]);
            if(count($rows)!==1){
                throw new RuntimeException('F184 view ownership lookup failed');
            }
            return [(string)$rows[0]->owner_name,(string)$rows[0]->acl];
        };
        $views=[
            'public.perf_f93_demo_campaign_account_read',
            'public.perf_f97_demo_review_read',
        ];
        $before=[];
        foreach($views as $view){
            $definition=(string)DB::scalar(
                'SELECT pg_get_viewdef(to_regclass(?), true)',[$view]
            );
            if($definition===''||str_contains($definition,'c.managed')
                ||!str_contains($definition,'perf_f91_tenant_for_login')){
                throw new RuntimeException('F184 unexpected original SQL view shape');
            }
            $before[$view]=$ownerAcl($view);
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW public.perf_f93_demo_campaign_account_read
WITH (security_barrier=true)
AS SELECT c.tenant_id,
          c.portal_service_ref,
          c.channel,
          c.campaign_ref,
          c.campaign_label,
          c.lifecycle_state,
          a.account_ref,
          l.link_state
FROM public.performance_campaign_account_links AS l
JOIN public.performance_managed_campaigns AS c
  ON c.id = l.campaign_id
 AND c.tenant_id = l.tenant_id
 AND c.portal_service_ref = l.portal_service_ref
 AND c.channel = l.channel
JOIN public.performance_ad_account_candidates AS a
  ON a.id = l.account_candidate_id
 AND a.tenant_id = l.tenant_id
 AND a.portal_service_ref = l.portal_service_ref
 AND a.channel = l.channel
WHERE l.tenant_id = public.perf_f91_tenant_for_login()
  AND c.tenant_id = public.perf_f91_tenant_for_login()
  AND a.tenant_id = public.perf_f91_tenant_for_login()
  AND c.managed = true
  AND c.dispatch_state = 'disabled'
  AND a.connection_state = 'unverified_demo'
  AND a.provider_connected = false
  AND a.dispatch_allowed = false
  AND a.billable = false
  AND l.link_state = 'unverified_demo'
  AND l.dispatch_allowed = false
  AND c.channel IN ('google_ads','meta')
  AND a.account_ref ~ '^demo_[a-z0-9][a-z0-9_-]{0,63}$'
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE VIEW public.perf_f97_demo_review_read
WITH (security_barrier=true)
AS
SELECT i.tenant_id,
       i.portal_service_ref,
       c.channel,
       c.campaign_ref,
       i.request_ref,
       i.operation,
       i.review_state,
       i.dispatch_state,
       j.review_state AS journal_review_state,
       (j.id IS NOT NULL) AS journaled
FROM public.performance_campaign_intents i
JOIN public.performance_managed_campaigns c
  ON c.id = i.campaign_id
 AND c.tenant_id = i.tenant_id
 AND c.portal_service_ref = i.portal_service_ref
LEFT JOIN public.performance_intent_review_journal j
  ON j.intent_id = i.id
 AND j.tenant_id = i.tenant_id
 AND j.portal_service_ref = i.portal_service_ref
 AND j.request_ref = i.request_ref
 AND j.review_state = i.review_state
 AND j.source = 'synthetic_postgresql_only'
 AND j.synthetic_only = true
WHERE i.tenant_id = public.perf_f91_tenant_for_login()
  AND c.tenant_id = public.perf_f91_tenant_for_login()
  AND c.managed = true
  AND i.portal_service_ref = 'svc-demo-f88'
  AND c.portal_service_ref = 'svc-demo-f88'
  AND c.channel IN ('google_ads','meta')
  AND c.dispatch_state = 'disabled'
  AND i.dispatch_state = 'disabled'
  AND i.manifest_version = 'manifest-demo-v1'
  AND (
    (i.review_state = 'awaiting_approval'
       AND i.reviewer_actor_ref IS NULL
       AND j.id IS NULL
       AND NOT EXISTS (
          SELECT 1 FROM public.performance_intent_review_journal AS invalid_j
          WHERE invalid_j.intent_id = i.id
       ))
    OR
    (i.review_state IN ('approved_internal','rejected')
       AND i.reviewer_actor_ref IS NOT NULL
       AND i.reviewer_actor_ref <> i.proposer_actor_ref
       AND j.id IS NOT NULL)
  )
SQL);
        foreach($views as $view){
            if($ownerAcl($view)!==$before[$view]){
                throw new RuntimeException('F184 migration changed view owner or grants');
            }
            $definition=(string)DB::scalar(
                'SELECT pg_get_viewdef(to_regclass(?), true)',[$view]
            );
            if(!str_contains($definition,'c.managed')){
                throw new RuntimeException('F184 fail: managed filter missing');
            }
        }
    }

    public function down():void
    {
        throw new RuntimeException(
            'F184 forbids automatic rollback to views that expose retired campaigns'
        );
    }
};
