<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F191 CI-only: increment versions for BOTH tenant revocation and service
 * link state/revision transitions, to detect ABA between two F189 reads.
 * This is local PG16 state, NEVER independent Portal IAM or external WORM.
 */
return new class extends Migration {
    public function up():void {
        if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='testing'
            ||getenv('PERF_F191_SERVICE_EPOCH_LAB')!=='1'
            ||getenv('PERF_F189_SERVICE_STATUS_LAB')!=='1'
            ||getenv('PGDATABASE')!=='perf_test_campaign_registry'
            ||getenv('PGHOST')!=='127.0.0.1'
            ||getenv('PGPORT')!=='5432'
            ||DB::connection()->getDriverName()!=='pgsql'
            ||DB::scalar('SELECT session_user')!=='postgres'
            ||DB::scalar('SELECT current_database()')!=='perf_test_campaign_registry'){
            throw new RuntimeException('F191 local PG16 CI required');
        }
        DB::unprepared(<<<'SQL'
ALTER TABLE public.performance_tenants
  ADD COLUMN status_epoch bigint NOT NULL DEFAULT 1
  CONSTRAINT perf_f191_tenant_status_epoch_ck CHECK(status_epoch BETWEEN 1 AND 1000000);
ALTER TABLE public.portal_service_links
  ADD COLUMN link_epoch bigint NOT NULL DEFAULT 1
  CONSTRAINT perf_f191_service_link_epoch_ck CHECK(link_epoch BETWEEN 1 AND 1000000);

CREATE FUNCTION public.perf_f191_bump_tenant_status_epoch()
RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER
SET search_path=pg_catalog AS $$
BEGIN
    IF NEW.status_epoch IS DISTINCT FROM OLD.status_epoch THEN
        RAISE EXCEPTION 'F191 tenant epoch is not caller-settable' USING ERRCODE='42501';
    END IF;
    IF NEW.status IS DISTINCT FROM OLD.status
        OR NEW.revoked_at IS DISTINCT FROM OLD.revoked_at THEN
        IF OLD.status_epoch>=999999 THEN
            RAISE EXCEPTION 'F191 tenant epoch exhausted' USING ERRCODE='22003';
        END IF;
        NEW.status_epoch:=OLD.status_epoch+1;
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER perf_f191_tenant_status_epoch_guard
BEFORE UPDATE ON public.performance_tenants
FOR EACH ROW EXECUTE FUNCTION public.perf_f191_bump_tenant_status_epoch();

CREATE FUNCTION public.perf_f191_bump_service_link_epoch()
RETURNS trigger LANGUAGE plpgsql SECURITY INVOKER
SET search_path=pg_catalog AS $$
BEGIN
    IF NEW.link_epoch IS DISTINCT FROM OLD.link_epoch THEN
        RAISE EXCEPTION 'F191 link epoch is not caller-settable' USING ERRCODE='42501';
    END IF;
    IF NEW.link_status IS DISTINCT FROM OLD.link_status
       OR NEW.source_revision IS DISTINCT FROM OLD.source_revision THEN
        IF OLD.link_epoch>=999999 THEN
            RAISE EXCEPTION 'F191 link epoch exhausted' USING ERRCODE='22003';
        END IF;
        NEW.link_epoch:=OLD.link_epoch+1;
    END IF;
    RETURN NEW;
END
$$;
CREATE TRIGGER perf_f191_service_link_epoch_guard
BEFORE UPDATE ON public.portal_service_links
FOR EACH ROW EXECUTE FUNCTION public.perf_f191_bump_service_link_epoch();

CREATE FUNCTION public.perf_f191_service_epoch_for_login()
RETURNS TABLE(tenant_epoch bigint,service_epoch bigint)
LANGUAGE sql STABLE SECURITY DEFINER
SET search_path=pg_catalog
AS $$
 SELECT t.status_epoch,l.link_epoch
 FROM public.performance_tenants t
 JOIN public.portal_service_links l
   ON l.tenant_id=t.id AND l.portal_service_ref='svc-demo-f88'
 WHERE current_database()='perf_test_campaign_registry'
   AND session_user::text IN ('perf_f91_reader_a','perf_f91_reader_b')
   AND t.id=public.perf_f91_tenant_for_login()
$$;
REVOKE ALL ON FUNCTION public.perf_f191_service_epoch_for_login() FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.perf_f191_service_epoch_for_login()
  TO perf_f91_reader_a,perf_f91_reader_b;
SQL);
        if((int)DB::scalar(
            "SELECT count(*) FROM pg_trigger WHERE tgname IN
             ('perf_f191_tenant_status_epoch_guard',
              'perf_f191_service_link_epoch_guard') AND tgenabled='O'"
        )!==2){
            throw new RuntimeException('F191 missing a mandatory SQL version trigger');
        }
    }

    public function down():void {
        throw new RuntimeException('F191 refuses silent rollback of service revision fencing');
    }
};
