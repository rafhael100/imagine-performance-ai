-- F1-11: fictitious Portal authority outbox, in perf_test_relay_authority ONLY.
-- This is a test schema, not a migration for operational Portal/Performance.
BEGIN;
CREATE TABLE perf_external_lab.mock_revocation_outbox (
    event_id text PRIMARY KEY CHECK (event_id ~ '^evt-demo-[a-z0-9-]{1,80}$'),
    service_ref text NOT NULL REFERENCES perf_external_lab.mock_authority_fences(service_ref),
    organization_ref text NOT NULL CHECK (organization_ref ~ '^org-demo-[a-z0-9-]{1,80}$'),
    tenant_ref text NOT NULL CHECK (tenant_ref ~ '^perf-demo-[a-z0-9-]{1,80}$'),
    source_revision integer NOT NULL CHECK (source_revision > 0),
    event_type text NOT NULL CHECK (event_type = 'service.authorization_changed.synthetic'),
    status text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'acked')),
    attempts integer NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    CONSTRAINT mock_outbox_service_revision_unique UNIQUE(service_ref, source_revision)
);
REVOKE ALL ON perf_external_lab.mock_revocation_outbox FROM PUBLIC;
GRANT SELECT ON perf_external_lab.mock_revocation_outbox TO perf_test_fence_reader;
GRANT USAGE ON SCHEMA perf_external_lab TO perf_test_outbox_acker;
GRANT SELECT (event_id, status, attempts) ON perf_external_lab.mock_revocation_outbox TO perf_test_outbox_acker;
GRANT UPDATE (status, attempts) ON perf_external_lab.mock_revocation_outbox TO perf_test_outbox_acker;
-- No write or ownership privileges over authority grants for the ack role.
COMMIT;
