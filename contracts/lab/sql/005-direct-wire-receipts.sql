-- F1-15 disposable PostgreSQL test only. The grant update and receipt
-- are committed as one local transaction; no remote side effects.
BEGIN;
CREATE TABLE perf_s2s_lab.mock_direct_wire_receipts (
  event_id text PRIMARY KEY CHECK (event_id ~ '^evt-demo-[a-f0-9]{24}$'),
  service_ref text NOT NULL CHECK (service_ref='svc-demo-b'),
  organization_ref text NOT NULL CHECK (organization_ref='org-demo-b'),
  tenant_ref text NOT NULL CHECK (tenant_ref='perf-demo-b'),
  grant_revision integer NOT NULL CHECK (grant_revision=2),
  key_id text NOT NULL CHECK (key_id='kid-demo-portal-1'),
  nonce text NOT NULL CHECK (nonce ~ '^nonce-demo-[a-z0-9-]{8,68}$'),
  body_sha256 char(64) NOT NULL CHECK (body_sha256 ~ '^[a-f0-9]{64}$'),
  received_at timestamptz NOT NULL DEFAULT clock_timestamp(),
  UNIQUE (key_id,nonce)
);
REVOKE ALL ON perf_s2s_lab.mock_direct_wire_receipts FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_s2s_lab TO perf_test_wire_ingress;
GRANT SELECT ON perf_s2s_lab.mock_service_grants TO perf_test_wire_ingress;
GRANT UPDATE(active,grant_revision,updated_at)
  ON perf_s2s_lab.mock_service_grants TO perf_test_wire_ingress;
GRANT SELECT, INSERT ON perf_s2s_lab.mock_direct_wire_receipts TO perf_test_wire_ingress;
COMMIT;
