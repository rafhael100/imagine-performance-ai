<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F185 TEST-ONLY: a retirement increments the campaign epoch and prevents
 * old account links/reviews from automatically returning on reactivation.
 * No real relink/approval workflow is authorized by this lab migration.
 */
return new class extends Migration {
    public function up():void {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F185_MANAGEMENT_EPOCH_LAB')!=='1'
            ||getenv('PERF_F184_MANAGED_VISIBILITY_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'){
            throw new RuntimeException('F185 requires disposable local PostgreSQL lab');
        }
        $views=[
            'public.perf_f93_demo_campaign_account_read',
            'public.perf_f97_demo_review_read'
        ];
        $acl=[];
        foreach($views as $view){
            $r=DB::select(
                "SELECT relowner::regrole::text AS owner,coalesce(relacl::text,'') AS acl
                 FROM pg_catalog.pg_class WHERE oid=to_regclass(?)",[$view]);
            if(count($r)!==1)throw new RuntimeException('F185 missing F184 view');
            $def=(string)DB::scalar('SELECT pg_get_viewdef(to_regclass(?),true)',[$view]);
            if(!str_contains($def,'c.managed')){
                throw new RuntimeException('F185 requires F184 retirement filter first');
            }
            $acl[$view]=[(string)$r[0]->owner,(string)$r[0]->acl];
        }

        DB::statement('ALTER TABLE public.performance_managed_campaigns
            ADD COLUMN management_epoch bigint NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE public.performance_campaign_account_links
            ADD COLUMN management_epoch bigint NOT NULL DEFAULT 1');
        DB::statement('ALTER TABLE public.performance_campaign_intents
            ADD COLUMN management_epoch bigint NOT NULL DEFAULT 1');
        foreach([
            'performance_managed_campaigns'=>'perf_f185_campaign_epoch_ck',
            'performance_campaign_account_links'=>'perf_f185_link_epoch_ck',
            'performance_campaign_intents'=>'perf_f185_intent_epoch_ck'
        ] as $table=>$constraint){
            DB::statement("ALTER TABLE public.{$table}
                 ADD CONSTRAINT {$constraint}
                 CHECK (management_epoch BETWEEN 1 AND 1000000)");
        }

        DB::unprepared(<<<'SQL'
CREATE FUNCTION public.perf_f185_advance_campaign_epoch()
RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER
SET search_path=pg_catalog AS $$
BEGIN
    IF NEW.management_epoch IS DISTINCT FROM OLD.management_epoch THEN
        RAISE EXCEPTION 'F185 management epoch is not caller-settable'
            USING ERRCODE='42501';
    END IF;
    IF OLD.managed=true AND NEW.managed=false THEN
        IF OLD.management_epoch>=999999 THEN
            RAISE EXCEPTION 'F185 epoch exhausted'
                USING ERRCODE='22003';
        END IF;
        NEW.management_epoch:=OLD.management_epoch+1;
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER perf_f185_retirement_epoch
BEFORE UPDATE ON public.performance_managed_campaigns
FOR EACH ROW EXECUTE FUNCTION public.perf_f185_advance_campaign_epoch();

CREATE FUNCTION public.perf_f185_immutable_child_epoch()
RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER
SET search_path=pg_catalog AS $$
BEGIN
    IF NEW.management_epoch IS DISTINCT FROM OLD.management_epoch THEN
        RAISE EXCEPTION 'F185 historic scope epoch cannot be edited'
            USING ERRCODE='42501';
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER perf_f185_link_epoch_immutable
BEFORE UPDATE ON public.performance_campaign_account_links
FOR EACH ROW EXECUTE FUNCTION public.perf_f185_immutable_child_epoch();
CREATE TRIGGER perf_f185_intent_epoch_immutable
BEFORE UPDATE ON public.performance_campaign_intents
FOR EACH ROW EXECUTE FUNCTION public.perf_f185_immutable_child_epoch();
SQL);

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
  AND c.managed=true
  AND l.management_epoch=c.management_epoch
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
AS SELECT i.tenant_id,
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
 AND j.synthetic_only=true
WHERE i.tenant_id = public.perf_f91_tenant_for_login()
  AND c.tenant_id = public.perf_f91_tenant_for_login()
  AND c.managed=true
  AND i.management_epoch=c.management_epoch
  AND i.portal_service_ref = 'svc-demo-f88'
  AND c.portal_service_ref = 'svc-demo-f88'
  AND c.channel IN ('google_ads','meta')
  AND c.dispatch_state = 'disabled'
  AND i.dispatch_state = 'disabled'
  AND i.manifest_version = 'manifest-demo-v1'
  AND (
    (i.review_state='awaiting_approval'
       AND i.reviewer_actor_ref IS NULL
       AND j.id IS NULL
       AND NOT EXISTS (
          SELECT 1 FROM public.performance_intent_review_journal AS invalid_j
          WHERE invalid_j.intent_id=i.id
       ))
    OR
    (i.review_state IN ('approved_internal','rejected')
       AND i.reviewer_actor_ref IS NOT NULL
       AND i.reviewer_actor_ref<>i.proposer_actor_ref
       AND j.id IS NOT NULL)
  )
SQL);
        foreach($views as $view){
            $r=DB::select(
                "SELECT relowner::regrole::text AS owner,coalesce(relacl::text,'') AS acl
                 FROM pg_catalog.pg_class WHERE oid=to_regclass(?)",[$view]);
            if(count($r)!==1
                ||[(string)$r[0]->owner,(string)$r[0]->acl]!==$acl[$view]){
                throw new RuntimeException('F185 view ownership or grants changed');
            }
            $def=(string)DB::scalar('SELECT pg_get_viewdef(to_regclass(?),true)',[$view]);
            if(!str_contains($def,'management_epoch')){
                throw new RuntimeException('F185 view missing generation guard');
            }
        }
    }

    public function down():void {
        throw new RuntimeException('F185 cannot downgrade to historical replayable views');
    }
};
