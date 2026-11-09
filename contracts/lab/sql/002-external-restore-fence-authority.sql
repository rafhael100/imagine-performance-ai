-- F1-10 isolated authority: must run ONLY in perf_test_fence_authority.
-- This database is deliberately EXCLUDED from main application's pg_dump.
-- A separate database in ONE disposable Postgres cluster is still NOT an
-- independent failure domain or an approved external authority in production.
BEGIN;
CREATE SCHEMA perf_external_lab;
REVOKE ALL ON SCHEMA perf_external_lab FROM PUBLIC;
CREATE TABLE perf_external_lab.mock_authority_fences (
    service_ref text PRIMARY KEY CHECK (service_ref ~ '^svc-demo-[a-z0-9-]{1,80}$'),
    organization_ref text NOT NULL CHECK (organization_ref ~ '^org-demo-[a-z0-9-]{1,80}$'),
    tenant_ref text NOT NULL CHECK (tenant_ref ~ '^perf-demo-[a-z0-9-]{1,80}$'),
    min_revision integer NOT NULL CHECK (min_revision > 0),
    active boolean NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON TABLE perf_external_lab.mock_authority_fences FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_external_lab TO perf_test_fence_reader;
GRANT SELECT ON TABLE perf_external_lab.mock_authority_fences TO perf_test_fence_reader;
-- No UPDATE, INSERT, DELETE, TRUNCATE or owner privileges for reader.
COMMIT;
