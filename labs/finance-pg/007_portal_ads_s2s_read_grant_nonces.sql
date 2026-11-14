-- Disposable PostgreSQL 18 CI only. Must be applied AFTER 005.
-- Portal Ads short-lived read grant nonces are NOT finance quote nonces.
-- Claims use a protected mapping from authenticated database session_user
-- to tenant; application GUC app.tenant_id is NEVER authoritative.
CREATE TABLE perf_fin_role_lab.ads_read_grant_nonces (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    nonce text NOT NULL CHECK (nonce ~ '^[A-Za-z0-9_-]{16,96}$'),
    expires_at bigint NOT NULL CHECK (expires_at > 0),
    claimed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id, nonce)
);

ALTER TABLE perf_fin_role_lab.ads_read_grant_nonces ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.ads_read_grant_nonces FORCE ROW LEVEL SECURITY;
CREATE POLICY session_owner_ads_read_nonce
  ON perf_fin_role_lab.ads_read_grant_nonces
  FOR ALL
  USING (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session())
  WITH CHECK (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session());

-- GRANT SELECT, INSERT only to the two mock tenant login roles in CI.
-- No UPDATE/DELETE privileges. No production migration nor nonce TTL pruning
-- until a trusted operator policy is designed/reviewed.
