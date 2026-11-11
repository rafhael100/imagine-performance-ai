-- FAST-PERF F1-164 / CI-ONLY PostgreSQL 16.
-- Must run after f162_pg16_tenant_review_registry_ci.sql.
-- Synthetic campaigns, not real Google/Meta accounts or approved contracts.
BEGIN;
CREATE TABLE perf_f162.managed_campaigns (
    tenant text NOT NULL CHECK(tenant ~ '^perf-demo-[a-z0-9-]{1,60}$'),
    campaign text NOT NULL CHECK(campaign ~ '^cam-[a-z0-9-]{1,60}$'),
    channel text NOT NULL CHECK(channel IN ('google_ads','meta')),
    managed boolean NOT NULL DEFAULT true,
    PRIMARY KEY(tenant,campaign)
);
ALTER TABLE perf_f162.managed_campaigns ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f162.managed_campaigns FORCE ROW LEVEL SECURITY;
CREATE POLICY f164_campaigns_tenant ON perf_f162.managed_campaigns
    FOR SELECT TO perf_f162_a,perf_f162_b
    USING(tenant=perf_f162.tenant_from_session());

INSERT INTO perf_f162.managed_campaigns(tenant,campaign,channel,managed) VALUES
    ('perf-demo-aurora','cam-f162-google','google_ads',true),
    ('perf-demo-aurora','cam-f163-search','google_ads',true),
    ('perf-demo-aurora','cam-f164-aurora','google_ads',true),
    ('perf-demo-boreal','cam-f162-boreal','google_ads',true),
    ('perf-demo-boreal','cam-f163-social','meta',true),
    ('perf-demo-boreal','cam-f164-boreal','meta',true);

REVOKE ALL ON perf_f162.managed_campaigns FROM PUBLIC;
GRANT SELECT ON perf_f162.managed_campaigns TO perf_f162_a,perf_f162_b;

CREATE FUNCTION perf_f162.guard_managed_campaign()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM perf_f162.managed_campaigns
        WHERE tenant=NEW.tenant
          AND campaign=NEW.campaign
          AND channel=NEW.channel
          AND managed=true
    ) THEN
        RAISE EXCEPTION 'F164 unrecognized, retired or cross-tenant campaign'
            USING ERRCODE='42501';
    END IF;
    RETURN NEW;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.guard_managed_campaign() FROM PUBLIC;

CREATE TRIGGER f164_managed_scope
    BEFORE INSERT OR UPDATE ON perf_f162.reviews
    FOR EACH ROW
    EXECUTE FUNCTION perf_f162.guard_managed_campaign();
COMMIT;
