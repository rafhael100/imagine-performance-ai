-- FAST-PERF F1-165. Disposable PostgreSQL16 test extension of F162+F164.
-- No operational migration, customer accounts, or advertiser capabilities.
BEGIN;

CREATE TABLE perf_f162.campaign_events (
    event_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tenant text NOT NULL,
    campaign text NOT NULL,
    channel text NOT NULL CHECK(channel IN ('google_ads','meta')),
    event_type text NOT NULL CHECK(event_type IN
        ('baseline_demo','registered_demo','retired_demo','reactivated_demo')),
    managed boolean NOT NULL,
    occurred_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    FOREIGN KEY (tenant,campaign)
        REFERENCES perf_f162.managed_campaigns(tenant,campaign) ON DELETE RESTRICT
);
CREATE INDEX f165_campaign_event_tenant_index
    ON perf_f162.campaign_events(tenant,event_id);
ALTER TABLE perf_f162.campaign_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f162.campaign_events FORCE ROW LEVEL SECURITY;
CREATE POLICY f165_event_tenant_read ON perf_f162.campaign_events
    FOR SELECT TO perf_f162_a,perf_f162_b
    USING (tenant=perf_f162.tenant_from_session());
REVOKE ALL ON perf_f162.campaign_events FROM PUBLIC;
GRANT SELECT ON perf_f162.campaign_events TO perf_f162_a,perf_f162_b;

-- F164 rows existed before campaign-event logging was installed.
INSERT INTO perf_f162.campaign_events(tenant,campaign,channel,event_type,managed)
    SELECT tenant,campaign,channel,'baseline_demo',managed
    FROM perf_f162.managed_campaigns ORDER BY tenant,campaign;

CREATE FUNCTION perf_f162.append_campaign_scope_event()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
DECLARE change_type text;
BEGIN
    IF TG_OP='UPDATE' THEN
        IF NEW.tenant IS DISTINCT FROM OLD.tenant
           OR NEW.campaign IS DISTINCT FROM OLD.campaign
           OR NEW.channel IS DISTINCT FROM OLD.channel THEN
            RAISE EXCEPTION 'F165 campaign binding immutable' USING ERRCODE='42501';
        END IF;
        IF NEW.managed IS NOT DISTINCT FROM OLD.managed THEN
            RETURN NEW;
        END IF;
        change_type:=CASE WHEN NEW.managed THEN 'reactivated_demo'
                          ELSE 'retired_demo' END;
    ELSE
        change_type:='registered_demo';
    END IF;
    INSERT INTO perf_f162.campaign_events
        (tenant,campaign,channel,event_type,managed)
    VALUES(NEW.tenant,NEW.campaign,NEW.channel,change_type,NEW.managed);
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.append_campaign_scope_event() FROM PUBLIC;
CREATE TRIGGER f165_campaign_audit
AFTER INSERT OR UPDATE ON perf_f162.managed_campaigns
FOR EACH ROW EXECUTE FUNCTION perf_f162.append_campaign_scope_event();

-- Row-lock the managed campaign in the same SQL transaction that changes
-- a review. Otherwise READ COMMITTED can race with retirement: old plain
-- SELECT could pass while the admin commits managed=false.
CREATE OR REPLACE FUNCTION perf_f162.guard_managed_campaign()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
DECLARE current_channel text;
BEGIN
    SELECT channel INTO current_channel
    FROM perf_f162.managed_campaigns
    WHERE tenant=NEW.tenant
      AND campaign=NEW.campaign
      AND channel=NEW.channel
      AND managed=true
    FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'F165 campaign outside active tenant management'
            USING ERRCODE='42501';
    END IF;
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.guard_managed_campaign() FROM PUBLIC;
COMMIT;
