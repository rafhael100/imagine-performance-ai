-- F1-71: Completely independent PostgreSQL container/database in CI.
-- This schema is NOT a WORM store, not immutable, not an actual signer,
-- and no production migration. Roles are independent of the F66 database.
\set ON_ERROR_STOP on
CREATE ROLE perf_f71_tenant_a LOGIN PASSWORD 'ci-f71-role-a-only'
  NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
CREATE ROLE perf_f71_tenant_b LOGIN PASSWORD 'ci-f71-role-b-only'
  NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
CREATE SCHEMA perf_f71_guard;
REVOKE ALL ON SCHEMA perf_f71_guard FROM PUBLIC;
CREATE TABLE perf_f71_guard.checkpoints (
    tenant_role text NOT NULL CHECK(
      tenant_role IN ('perf_f66_tenant_a','perf_f66_tenant_b')
    ),
    kind text NOT NULL CHECK(kind IN ('reserve','swap')),
    ref text NOT NULL CHECK(ref ~ '^(cam-|req-demo-)[a-z0-9-]{1,60}$'),
    channel text CHECK(channel IN ('google_ads','meta')),
    old_ref text CHECK(old_ref ~ '^cam-[a-z0-9-]{1,60}$'),
    new_ref text CHECK(new_ref ~ '^cam-[a-z0-9-]{1,60}$'),
    PRIMARY KEY (tenant_role,kind,ref),
    CONSTRAINT f71_kind_fields CHECK (
      (kind='reserve' AND ref LIKE 'cam-%'
        AND channel IS NOT NULL AND old_ref IS NULL AND new_ref IS NULL)
      OR
      (kind='swap' AND ref LIKE 'req-demo-%'
        AND channel IS NULL AND old_ref IS NOT NULL AND new_ref IS NOT NULL)
    )
);
ALTER TABLE perf_f71_guard.checkpoints ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f71_guard.checkpoints FORCE ROW LEVEL SECURITY;
CREATE POLICY f71_reader_scope ON perf_f71_guard.checkpoints
 FOR SELECT USING (
    tenant_role=CASE session_user
      WHEN 'perf_f71_tenant_a' THEN 'perf_f66_tenant_a'
      WHEN 'perf_f71_tenant_b' THEN 'perf_f66_tenant_b'
      ELSE NULL END
 );
REVOKE ALL ON perf_f71_guard.checkpoints FROM PUBLIC;
GRANT CONNECT ON DATABASE perf_f71_ci TO perf_f71_tenant_a,perf_f71_tenant_b;
GRANT USAGE ON SCHEMA perf_f71_guard TO perf_f71_tenant_a,perf_f71_tenant_b;
GRANT SELECT ON perf_f71_guard.checkpoints
 TO perf_f71_tenant_a,perf_f71_tenant_b;
-- NO INSERT/UPDATE/DELETE or bypass to either reader role.
-- Admin seeds test markers in CI only, after verifying F66 mock commits.
