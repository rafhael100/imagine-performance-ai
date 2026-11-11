-- FAST-PERF F1-167: PostgreSQL16 disposable CI-only generation fence.
-- Existing review rows receive epoch 0 ("legacy unverified") and remain
-- quarantined: no guessed historical trust or automatic resurrection.
BEGIN;

ALTER TABLE perf_f162.managed_campaigns ADD COLUMN scope_epoch integer NOT NULL
    DEFAULT 1 CHECK (scope_epoch BETWEEN 1 AND 10000);
-- At migration time, reconstruct known campaign generation from recorded
-- retire/reactivate transitions. Updating only scope_epoch does not create
-- false audit events because F165 logs actual managed-state transitions.
UPDATE perf_f162.managed_campaigns AS m SET scope_epoch=1+(
    SELECT count(*)::integer FROM perf_f162.campaign_events AS e
    WHERE e.tenant=m.tenant AND e.campaign=m.campaign
      AND e.event_type IN ('retired_demo','reactivated_demo')
);

-- Every PRE-F167 review remains quarantined: there is no trusted review
-- generation recorded in the old schema.
ALTER TABLE perf_f162.reviews ADD COLUMN scope_epoch integer NOT NULL
    DEFAULT 0 CHECK(scope_epoch BETWEEN 0 AND 10000);

ALTER TABLE perf_f162.campaign_events ADD COLUMN scope_epoch integer NOT NULL
    DEFAULT 1 CHECK(scope_epoch BETWEEN 1 AND 10000);
-- Historical event epoch=1 is a legacy placeholder, not a reconstructed
-- trusted generation. Future event rows use the actual transaction epoch.

CREATE FUNCTION perf_f162.f167_assign_campaign_epoch()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
BEGIN
    IF TG_OP='INSERT' THEN
        NEW.scope_epoch:=1;
        RETURN NEW;
    END IF;
    IF NEW.tenant IS DISTINCT FROM OLD.tenant
       OR NEW.campaign IS DISTINCT FROM OLD.campaign
       OR NEW.channel IS DISTINCT FROM OLD.channel THEN
        RAISE EXCEPTION 'F167 cannot rebind campaign scope'
            USING ERRCODE='42501';
    END IF;
    IF NEW.managed IS DISTINCT FROM OLD.managed THEN
        IF OLD.scope_epoch>=10000 THEN
            RAISE EXCEPTION 'F167 exhausted synthetic generation range'
                USING ERRCODE='23514';
        END IF;
        NEW.scope_epoch:=OLD.scope_epoch+1;
    ELSE
        NEW.scope_epoch:=OLD.scope_epoch;
    END IF;
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.f167_assign_campaign_epoch() FROM PUBLIC;
CREATE TRIGGER f167_campaign_epoch
BEFORE INSERT OR UPDATE ON perf_f162.managed_campaigns
FOR EACH ROW EXECUTE FUNCTION perf_f162.f167_assign_campaign_epoch();

CREATE OR REPLACE FUNCTION perf_f162.append_campaign_scope_event()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
DECLARE change_type text;
BEGIN
    IF TG_OP='UPDATE' THEN
        IF NEW.tenant IS DISTINCT FROM OLD.tenant
           OR NEW.campaign IS DISTINCT FROM OLD.campaign
           OR NEW.channel IS DISTINCT FROM OLD.channel THEN
            RAISE EXCEPTION 'F167 campaign binding immutable'
                USING ERRCODE='42501';
        END IF;
        IF NEW.managed IS NOT DISTINCT FROM OLD.managed THEN RETURN NEW; END IF;
        change_type:=CASE WHEN NEW.managed THEN 'reactivated_demo'
                          ELSE 'retired_demo' END;
    ELSE
        change_type:='registered_demo';
    END IF;
    INSERT INTO perf_f162.campaign_events
        (tenant,campaign,channel,event_type,managed,scope_epoch)
    VALUES(NEW.tenant,NEW.campaign,NEW.channel,change_type,
           NEW.managed,NEW.scope_epoch);
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.append_campaign_scope_event() FROM PUBLIC;

CREATE FUNCTION perf_f162.f167_guard_review_epoch()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
DECLARE active_epoch integer;
BEGIN
    SELECT scope_epoch INTO active_epoch
    FROM perf_f162.managed_campaigns
    WHERE tenant=NEW.tenant
      AND campaign=NEW.campaign
      AND channel=NEW.channel
      AND managed=true
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'F167 campaign not under active management'
            USING ERRCODE='42501';
    END IF;
    IF TG_OP='INSERT' THEN
        -- Ignore untrusted scope_epoch supplied in INSERT.
        NEW.scope_epoch:=active_epoch;
        RETURN NEW;
    END IF;
    IF OLD.scope_epoch=0 OR OLD.scope_epoch<>active_epoch
       OR NEW.scope_epoch<>OLD.scope_epoch THEN
        RAISE EXCEPTION 'F167 stale or forged review scope generation'
            USING ERRCODE='42501';
    END IF;
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.f167_guard_review_epoch() FROM PUBLIC;
CREATE TRIGGER f167_review_epoch
BEFORE INSERT OR UPDATE ON perf_f162.reviews
FOR EACH ROW EXECUTE FUNCTION perf_f162.f167_guard_review_epoch();

-- Active read projection includes only a revision created during the
-- currently managed generation. Old approvals are never resurrected.
CREATE OR REPLACE VIEW perf_f162.active_review_dashboard
WITH (security_invoker=true,security_barrier=true) AS
    SELECT r.tenant,r.reference,r.campaign,r.channel,r.title,r.status,r.version
    FROM perf_f162.reviews AS r
    INNER JOIN perf_f162.managed_campaigns AS c
      ON c.tenant=r.tenant AND c.campaign=r.campaign AND c.channel=r.channel
    WHERE c.managed=true AND r.scope_epoch>0
      AND r.scope_epoch=c.scope_epoch;

CREATE OR REPLACE VIEW perf_f162.retired_review_counts
WITH (security_invoker=true,security_barrier=true) AS
    SELECT r.tenant,r.channel,count(*)::integer AS count_retired_demo
    FROM perf_f162.reviews AS r
    INNER JOIN perf_f162.managed_campaigns AS c
      ON c.tenant=r.tenant AND c.campaign=r.campaign AND c.channel=r.channel
    WHERE c.managed=false OR r.scope_epoch=0
       OR r.scope_epoch<>c.scope_epoch
    GROUP BY r.tenant,r.channel;
COMMIT;
