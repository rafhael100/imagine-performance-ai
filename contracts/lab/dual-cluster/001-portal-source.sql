-- F1-20 SOURCE: run ONLY on portal_f20_test, PostgreSQL port 5432 in CI.
-- A different container owns the destination on published port 5433.
BEGIN;
CREATE SCHEMA portal_f20;
CREATE TABLE portal_f20.mock_authority (
  service_ref text PRIMARY KEY CHECK (service_ref ~ '^svc-demo-[ab]$'),
  organization_ref text NOT NULL CHECK (organization_ref ~ '^org-demo-[ab]$'),
  tenant_ref text NOT NULL CHECK (tenant_ref ~ '^perf-demo-[ab]$'),
  current_revision integer NOT NULL CHECK (current_revision > 0),
  recovery_epoch integer NOT NULL CHECK (recovery_epoch > 0),
  active boolean NOT NULL
);
INSERT INTO portal_f20.mock_authority
(service_ref,organization_ref,tenant_ref,current_revision,recovery_epoch,active)
VALUES
('svc-demo-a','org-demo-a','perf-demo-a',7,8,false),
('svc-demo-b','org-demo-b','perf-demo-b',1,1,true);
REVOKE ALL ON SCHEMA portal_f20 FROM PUBLIC;
REVOKE ALL ON TABLE portal_f20.mock_authority FROM PUBLIC;
ALTER TABLE portal_f20.mock_authority ENABLE ROW LEVEL SECURITY;
ALTER TABLE portal_f20.mock_authority FORCE ROW LEVEL SECURITY;
CREATE POLICY portal_f20_reader_b ON portal_f20.mock_authority
FOR SELECT TO portal_f20_reader USING (
  service_ref='svc-demo-b'
  AND organization_ref='org-demo-b'
  AND tenant_ref='perf-demo-b'
);
GRANT USAGE ON SCHEMA portal_f20 TO portal_f20_reader;
GRANT SELECT ON portal_f20.mock_authority TO portal_f20_reader;
COMMIT;
