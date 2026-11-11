-- FAST-PERF F1-162 / CI-ONLY disposable PostgreSQL 16.
-- Not production schema, migrations, IAM, real accounts or Ads.
BEGIN;
CREATE ROLE perf_f162_a LOGIN PASSWORD 'ci-only-perf-f162-a'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
CREATE ROLE perf_f162_b LOGIN PASSWORD 'ci-only-perf-f162-b'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;

CREATE SCHEMA perf_f162;
REVOKE ALL ON SCHEMA perf_f162 FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f162 TO perf_f162_a,perf_f162_b;

CREATE TABLE perf_f162.principals (
    login_name name PRIMARY KEY,
    tenant text NOT NULL UNIQUE
        CHECK (tenant ~ '^perf-demo-[a-z0-9-]{1,60}$')
);
INSERT INTO perf_f162.principals(login_name,tenant) VALUES
 ('perf_f162_a','perf-demo-aurora'),
 ('perf_f162_b','perf-demo-boreal');
REVOKE ALL ON perf_f162.principals FROM PUBLIC;

CREATE FUNCTION perf_f162.tenant_from_session()
RETURNS text LANGUAGE sql STABLE SECURITY DEFINER
SET search_path = pg_catalog, perf_f162 AS $$
    SELECT tenant FROM perf_f162.principals WHERE login_name=session_user
$$;
REVOKE ALL ON FUNCTION perf_f162.tenant_from_session() FROM PUBLIC;
GRANT EXECUTE ON FUNCTION perf_f162.tenant_from_session()
    TO perf_f162_a,perf_f162_b;

CREATE TABLE perf_f162.reviews (
    tenant text NOT NULL,
    reference text NOT NULL CHECK(reference ~ '^rev-[a-z0-9-]{1,60}$'),
    campaign text NOT NULL CHECK(campaign ~ '^cam-[a-z0-9-]{1,60}$'),
    channel text NOT NULL CHECK(channel IN ('google_ads','meta')),
    title text NOT NULL CHECK(length(title)>0 AND length(title)<=100
        AND title !~ '[<>[:cntrl:]]'),
    status text NOT NULL DEFAULT 'pending_demo'
        CHECK(status IN ('pending_demo','changes_requested_demo',
                         'approved_mock_only','rejected_mock_only')),
    version integer NOT NULL DEFAULT 1
        CHECK(version BETWEEN 1 AND 1000),
    PRIMARY KEY(tenant,reference)
);
CREATE TABLE perf_f162.events (
    event_id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tenant text NOT NULL,
    reference text NOT NULL,
    transition text NOT NULL
        CHECK(transition IN ('submitted_demo','changes_requested_demo',
                             'resubmitted_demo','approved_mock_only',
                             'rejected_mock_only')),
    version integer NOT NULL CHECK(version BETWEEN 1 AND 1000),
    reviewer_fixture text NOT NULL DEFAULT 'reviewer-demo-pg-lab',
    UNIQUE(tenant,reference,version),
    FOREIGN KEY(tenant,reference)
        REFERENCES perf_f162.reviews(tenant,reference) ON DELETE RESTRICT
);
CREATE INDEX f162_event_tenant_order ON perf_f162.events(tenant,event_id);

CREATE FUNCTION perf_f162.guard_review_transition()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, perf_f162 AS $$
BEGIN
    IF TG_OP='INSERT' THEN
        IF NEW.status<>'pending_demo' OR NEW.version<>1 THEN
            RAISE EXCEPTION 'F162 inserts must begin pending/1' USING ERRCODE='42501';
        END IF;
        -- Serialize synthetic quota checks across concurrent writers.
        PERFORM pg_advisory_xact_lock(hashtext(NEW.tenant));
        IF (SELECT count(*) FROM perf_f162.reviews WHERE tenant=NEW.tenant)>=25 THEN
            RAISE EXCEPTION 'F162 fictional review cap reached' USING ERRCODE='23514';
        END IF;
        RETURN NEW;
    END IF;
    IF NEW.tenant IS DISTINCT FROM OLD.tenant
       OR NEW.reference IS DISTINCT FROM OLD.reference
       OR NEW.campaign IS DISTINCT FROM OLD.campaign
       OR NEW.channel IS DISTINCT FROM OLD.channel
       OR NEW.version<>OLD.version+1 THEN
        RAISE EXCEPTION 'F162 immutable scope or version bypass' USING ERRCODE='42501';
    END IF;
    IF OLD.status='pending_demo'
       AND NEW.status IN ('changes_requested_demo','approved_mock_only','rejected_mock_only')
       AND NEW.title=OLD.title THEN
        RETURN NEW;
    ELSIF OLD.status='changes_requested_demo'
       AND NEW.status='pending_demo' THEN
        RETURN NEW;
    END IF;
    RAISE EXCEPTION 'F162 illegal state transition' USING ERRCODE='42501';
END
$$;
CREATE TRIGGER f162_guard BEFORE INSERT OR UPDATE
ON perf_f162.reviews FOR EACH ROW
EXECUTE FUNCTION perf_f162.guard_review_transition();

CREATE FUNCTION perf_f162.append_review_event()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, perf_f162 AS $$
DECLARE
    step text;
BEGIN
    IF TG_OP='INSERT' THEN step:='submitted_demo';
    ELSIF NEW.status='pending_demo' THEN step:='resubmitted_demo';
    ELSE step:=NEW.status;
    END IF;
    INSERT INTO perf_f162.events(tenant,reference,transition,version,reviewer_fixture)
    VALUES(NEW.tenant,NEW.reference,step,NEW.version,'reviewer-demo-pg-lab');
    RETURN NEW;
END
$$;
CREATE TRIGGER f162_audit AFTER INSERT OR UPDATE
ON perf_f162.reviews FOR EACH ROW
EXECUTE FUNCTION perf_f162.append_review_event();

ALTER TABLE perf_f162.reviews ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f162.reviews FORCE ROW LEVEL SECURITY;
ALTER TABLE perf_f162.events ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f162.events FORCE ROW LEVEL SECURITY;
CREATE POLICY f162_reviews_tenant ON perf_f162.reviews
    FOR ALL TO perf_f162_a,perf_f162_b
    USING(tenant=perf_f162.tenant_from_session())
    WITH CHECK(tenant=perf_f162.tenant_from_session());
CREATE POLICY f162_events_tenant ON perf_f162.events
    FOR SELECT TO perf_f162_a,perf_f162_b
    USING(tenant=perf_f162.tenant_from_session());

REVOKE ALL ON ALL TABLES IN SCHEMA perf_f162 FROM PUBLIC;
REVOKE ALL ON FUNCTION perf_f162.guard_review_transition() FROM PUBLIC;
REVOKE ALL ON FUNCTION perf_f162.append_review_event() FROM PUBLIC;
GRANT SELECT,INSERT ON perf_f162.reviews TO perf_f162_a,perf_f162_b;
GRANT UPDATE(title,status,version) ON perf_f162.reviews TO perf_f162_a,perf_f162_b;
GRANT SELECT ON perf_f162.events TO perf_f162_a,perf_f162_b;
COMMIT;
