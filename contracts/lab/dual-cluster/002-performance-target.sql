-- F1-20 DESTINATION: run ONLY on perf_f20_test, PG published port 5433.
BEGIN;
CREATE SCHEMA perf_f20;
CREATE TABLE perf_f20.mock_grants (
  service_ref text PRIMARY KEY CHECK (service_ref ~ '^svc-demo-[ab]$'),
  organization_ref text NOT NULL CHECK (organization_ref ~ '^org-demo-[ab]$'),
  tenant_ref text NOT NULL CHECK (tenant_ref ~ '^perf-demo-[ab]$'),
  grant_revision integer NOT NULL CHECK (grant_revision > 0),
  recovery_epoch integer NOT NULL CHECK (recovery_epoch > 0),
  active boolean NOT NULL
);
CREATE TABLE perf_f20.mock_receipts (
  event_id text PRIMARY KEY CHECK (event_id ~ '^evt-demo-[a-z0-9-]{1,80}$'),
  service_ref text NOT NULL REFERENCES perf_f20.mock_grants(service_ref),
  grant_revision integer NOT NULL CHECK (grant_revision > 0)
);
INSERT INTO perf_f20.mock_grants
(service_ref,organization_ref,tenant_ref,grant_revision,recovery_epoch,active)
VALUES
('svc-demo-a','org-demo-a','perf-demo-a',7,8,false),
('svc-demo-b','org-demo-b','perf-demo-b',1,1,true);
REVOKE ALL ON SCHEMA perf_f20 FROM PUBLIC;
REVOKE ALL ON TABLE perf_f20.mock_grants FROM PUBLIC;
REVOKE ALL ON TABLE perf_f20.mock_receipts FROM PUBLIC;
ALTER TABLE perf_f20.mock_grants ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f20.mock_grants FORCE ROW LEVEL SECURITY;
CREATE POLICY perf_f20_writer_b ON perf_f20.mock_grants
FOR ALL TO perf_f20_writer USING (
  service_ref='svc-demo-b'
  AND organization_ref='org-demo-b'
  AND tenant_ref='perf-demo-b'
) WITH CHECK (
  service_ref='svc-demo-b'
  AND organization_ref='org-demo-b'
  AND tenant_ref='perf-demo-b'
);
GRANT USAGE ON SCHEMA perf_f20 TO perf_f20_writer;
GRANT SELECT ON perf_f20.mock_grants TO perf_f20_writer;
GRANT UPDATE(active,grant_revision,recovery_epoch)
  ON perf_f20.mock_grants TO perf_f20_writer;
GRANT SELECT ON perf_f20.mock_receipts TO perf_f20_writer;
COMMIT;
