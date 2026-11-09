-- F1-08 PRIVATE POSTGRESQL LAB ONLY. Run only in disposable perf_test_* DB.
-- Do not install as Laravel migration or expose SECURITY DEFINER to real traffic.
BEGIN;
CREATE SCHEMA perf_s2s_lab;
REVOKE ALL ON SCHEMA perf_s2s_lab FROM PUBLIC;

CREATE TABLE perf_s2s_lab.mock_service_grants (
    service_ref text PRIMARY KEY CHECK (service_ref ~ '^svc-demo-[a-z0-9-]{1,80}$'),
    organization_ref text NOT NULL CHECK (organization_ref ~ '^org-demo-[a-z0-9-]{1,80}$'),
    tenant_ref text NOT NULL CHECK (tenant_ref ~ '^perf-demo-[a-z0-9-]{1,80}$'),
    grant_revision integer NOT NULL CHECK (grant_revision > 0),
    active boolean NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

CREATE TABLE perf_s2s_lab.mock_replay_receipts (
    producer text NOT NULL CHECK (producer = 'portal-imagine-mock'),
    request_id text NOT NULL CHECK (request_id ~ '^req-demo-[a-z0-9-]{1,80}$'),
    nonce text NOT NULL CHECK (nonce ~ '^nonce-demo-[a-z0-9-]{1,80}$'),
    service_ref text NOT NULL REFERENCES perf_s2s_lab.mock_service_grants(service_ref),
    tenant_ref text NOT NULL,
    grant_revision integer NOT NULL CHECK (grant_revision > 0),
    payload_sha256 char(64) NOT NULL CHECK (payload_sha256 ~ '^[0-9a-f]{64}$'),
    inserted_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT mock_global_request_unique UNIQUE (producer, request_id),
    CONSTRAINT mock_global_nonce_unique UNIQUE (producer, nonce)
);

-- LAB ONLY: caller may invoke this with self-asserted IDs. The DB function
-- DOES NOT verify a signature or authenticate the calling workload.
CREATE FUNCTION perf_s2s_lab.mock_claim(
    p_organization text, p_tenant text, p_service text,
    p_revision integer, p_producer text, p_request text,
    p_nonce text, p_digest text
)
RETURNS text
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = pg_catalog
AS $body$
DECLARE
    g record;
    inserted integer;
BEGIN
    IF p_organization IS NULL OR p_organization !~ '^org-demo-[a-z0-9-]{1,80}$'
       OR p_tenant IS NULL OR p_tenant !~ '^perf-demo-[a-z0-9-]{1,80}$'
       OR p_service IS NULL OR p_service !~ '^svc-demo-[a-z0-9-]{1,80}$'
       OR p_revision IS NULL OR p_revision <= 0
       OR p_producer IS DISTINCT FROM 'portal-imagine-mock'
       OR p_request IS NULL OR p_request !~ '^req-demo-[a-z0-9-]{1,80}$'
       OR p_nonce IS NULL OR p_nonce !~ '^nonce-demo-[a-z0-9-]{1,80}$'
       OR p_digest IS NULL OR p_digest !~ '^[0-9a-f]{64}$'
    THEN
        RETURN 'invalid_lab_input';
    END IF;

    -- A revoke UPDATE and a claim serialize on the same row lock.
    SELECT organization_ref, tenant_ref, grant_revision, active INTO g
      FROM perf_s2s_lab.mock_service_grants
      WHERE service_ref = p_service FOR UPDATE;
    IF NOT FOUND THEN
        RETURN 'not_found';
    END IF;
    IF g.organization_ref <> p_organization OR g.tenant_ref <> p_tenant THEN
        RETURN 'not_found';
    END IF;
    IF NOT g.active THEN
        RETURN 'mock_grant_revoked';
    END IF;
    IF g.grant_revision <> p_revision THEN
        RETURN 'mock_grant_revision_stale';
    END IF;

    INSERT INTO perf_s2s_lab.mock_replay_receipts (
        producer, request_id, nonce, service_ref, tenant_ref,
        grant_revision, payload_sha256
    )
    VALUES (
        p_producer, p_request, p_nonce, p_service, p_tenant,
        p_revision, p_digest
    )
    ON CONFLICT DO NOTHING;
    GET DIAGNOSTICS inserted = ROW_COUNT;
    IF inserted = 0 THEN
        RETURN 'mock_replay_denied';
    END IF;
    RETURN 'accepted_synthetic_only';
END
$body$;

REVOKE ALL ON FUNCTION perf_s2s_lab.mock_claim(text,text,text,integer,text,text,text,text) FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_s2s_lab TO perf_s2s_lab_caller;
GRANT EXECUTE ON FUNCTION perf_s2s_lab.mock_claim(text,text,text,integer,text,text,text,text)
    TO perf_s2s_lab_caller;
-- No table access granted to the caller, no permissions to amend revocations.
COMMIT;
