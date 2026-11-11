<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F186 CI-only: make reactivation fail-closed on the write side.
 * - Fresh INSERT into an old, retired/reactivated campaign is rejected.
 * - Old link/approval DELETE cannot erase an historical generation.
 * - Campaign retirement/reactivation transitions get an append-only SQL
 *   audit record, still not independent from a privileged DB restore.
 */
return new class extends Migration {
    public function up():void {
        if(PHP_SAPI!=='cli'
            ||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F186_EPOCH_WRITE_AUDIT_LAB')!=='1'
            ||getenv('PERF_F185_MANAGEMENT_EPOCH_LAB')!=='1'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'){
            throw new RuntimeException('F186 only allowed in disposable PostgreSQL16 CI');
        }
        foreach([
            'performance_managed_campaigns',
            'performance_campaign_account_links',
            'performance_campaign_intents'
        ] as $table){
            $present=DB::scalar(
                'SELECT count(*) FROM information_schema.columns
                 WHERE table_schema=? AND table_name=? AND column_name=?',
                ['public',$table,'management_epoch']
            );
            if((int)$present!==1){
                throw new RuntimeException('F186 depends on F185 epoch columns');
            }
        }

        DB::unprepared(<<<'SQL'
CREATE TABLE public.performance_f186_management_transition_audit (
    event_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tenant_id uuid NOT NULL,
    campaign_id uuid NOT NULL,
    previous_epoch bigint NOT NULL CHECK(previous_epoch BETWEEN 1 AND 1000000),
    current_epoch bigint NOT NULL CHECK(current_epoch BETWEEN 1 AND 1000000),
    previous_managed boolean NOT NULL,
    current_managed boolean NOT NULL,
    recorded_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT perf_f186_actual_change_ck CHECK(previous_managed<>current_managed),
    CONSTRAINT perf_f186_transition_ck CHECK(
        (previous_managed=true AND current_managed=false AND
         current_epoch=previous_epoch+1)
      OR
        (previous_managed=false AND current_managed=true AND
         current_epoch=previous_epoch)
    )
);
REVOKE ALL ON TABLE public.performance_f186_management_transition_audit FROM PUBLIC;
ALTER TABLE public.performance_f186_management_transition_audit ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.performance_f186_management_transition_audit FORCE ROW LEVEL SECURITY;

CREATE FUNCTION public.perf_f186_require_fresh_insert()
RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER
SET search_path=pg_catalog AS $$
DECLARE current_epoch bigint;
DECLARE currently_managed boolean;
BEGIN
    IF TG_TABLE_NAME='performance_campaign_account_links' THEN
        SELECT c.management_epoch,c.managed
          INTO current_epoch,currently_managed
        FROM public.performance_managed_campaigns AS c
        WHERE c.id=NEW.campaign_id AND c.tenant_id=NEW.tenant_id
          AND c.portal_service_ref=NEW.portal_service_ref
          AND c.channel=NEW.channel;
    ELSIF TG_TABLE_NAME='performance_campaign_intents' THEN
        SELECT c.management_epoch,c.managed
          INTO current_epoch,currently_managed
        FROM public.performance_managed_campaigns AS c
        WHERE c.id=NEW.campaign_id AND c.tenant_id=NEW.tenant_id
          AND c.portal_service_ref=NEW.portal_service_ref;
    ELSE
        RAISE EXCEPTION 'F186 invalid scope table' USING ERRCODE='42501';
    END IF;
    -- New records in epoch 1 (never retired) can still follow the normal
    -- already-reviewed synthetic write path. A reactivated campaign in
    -- epoch 2+ MUST go through a separate future verified authorization
    -- workflow, not a new raw INSERT with copied old approval data.
    IF current_epoch IS NULL OR currently_managed IS DISTINCT FROM true
       OR current_epoch<>1 OR NEW.management_epoch<>1 THEN
        RAISE EXCEPTION 'F186 stale/reissued management scope not authorized'
            USING ERRCODE='42501';
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER perf_f186_link_insert_guard
BEFORE INSERT ON public.performance_campaign_account_links
FOR EACH ROW EXECUTE FUNCTION public.perf_f186_require_fresh_insert();
CREATE TRIGGER perf_f186_intent_insert_guard
BEFORE INSERT ON public.performance_campaign_intents
FOR EACH ROW EXECUTE FUNCTION public.perf_f186_require_fresh_insert();

CREATE FUNCTION public.perf_f186_preserve_old_row()
RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER
SET search_path=pg_catalog AS $$
BEGIN
    RAISE EXCEPTION 'F186 old scope history cannot be deleted'
        USING ERRCODE='42501';
END
$$;
CREATE TRIGGER perf_f186_no_link_delete
BEFORE DELETE ON public.performance_campaign_account_links
FOR EACH ROW EXECUTE FUNCTION public.perf_f186_preserve_old_row();
CREATE TRIGGER perf_f186_no_intent_delete
BEFORE DELETE ON public.performance_campaign_intents
FOR EACH ROW EXECUTE FUNCTION public.perf_f186_preserve_old_row();
CREATE TRIGGER perf_f186_no_audit_update_or_delete
BEFORE UPDATE OR DELETE ON public.performance_f186_management_transition_audit
FOR EACH ROW EXECUTE FUNCTION public.perf_f186_preserve_old_row();

CREATE FUNCTION public.perf_f186_record_management_transition()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog AS $$
BEGIN
    IF OLD.managed IS DISTINCT FROM NEW.managed THEN
        INSERT INTO public.performance_f186_management_transition_audit (
            tenant_id,campaign_id,previous_epoch,current_epoch,
            previous_managed,current_managed
        ) VALUES (
            NEW.tenant_id,NEW.id,OLD.management_epoch,NEW.management_epoch,
            OLD.managed,NEW.managed
        );
    END IF;
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION public.perf_f186_record_management_transition() FROM PUBLIC;
CREATE TRIGGER perf_f186_audit_management_transition
AFTER UPDATE OF managed ON public.performance_managed_campaigns
FOR EACH ROW EXECUTE FUNCTION public.perf_f186_record_management_transition();
SQL);
        $count=(int)DB::scalar(
            "SELECT count(*) FROM pg_trigger
             WHERE tgname IN (
                'perf_f186_link_insert_guard',
                'perf_f186_intent_insert_guard',
                'perf_f186_no_link_delete',
                'perf_f186_no_intent_delete',
                'perf_f186_no_audit_update_or_delete',
                'perf_f186_audit_management_transition'
             ) AND tgenabled='O'"
        );
        if($count!==6)throw new RuntimeException('F186 missing any of six SQL gates');
    }

    public function down():void {
        throw new RuntimeException('F186 refuses rollback to scope write replay');
    }
};
