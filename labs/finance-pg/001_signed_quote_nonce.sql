-- PostgreSQL lab only. Isolated from production Portal and Performance databases.
-- Apply only to a disposable CI database controlled by the test runner.
CREATE SCHEMA IF NOT EXISTS perf_fin_lab;
CREATE TABLE IF NOT EXISTS perf_fin_lab.signed_quote_nonces (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    nonce text NOT NULL CHECK (nonce ~ '^[A-Za-z0-9_-]{16,96}$'),
    key_id text NOT NULL CHECK (key_id ~ '^k_[A-Za-z0-9_-]{4,64}$'),
    expires_at bigint NOT NULL CHECK (expires_at > 0),
    claimed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id, nonce)
);
-- Unique by (tenant,nonce), NOT by key. Rotation does not allow nonce replay.
ALTER TABLE perf_fin_lab.signed_quote_nonces ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_lab.signed_quote_nonces FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_nonces ON perf_fin_lab.signed_quote_nonces;
CREATE POLICY tenant_nonces ON perf_fin_lab.signed_quote_nonces
  FOR ALL
  USING (tenant_id = current_setting('app.tenant_id', true))
  WITH CHECK (tenant_id = current_setting('app.tenant_id', true));
-- The CI setup creates a dedicated non-owner, non-BYPASSRLS role.
-- Do not grant UPDATE/DELETE, CREATE on schema or TRUNCATE to the runtime role.
