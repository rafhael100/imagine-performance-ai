-- CI-only PostgreSQL18 disposable lab. Run after 005 role/session identity.
-- V7 signed PLAN agreement nonces are NOT financial quote, Ads-read grant,
-- activation F419 or client contract records. Never migrate this table to VPS.
CREATE TABLE perf_fin_role_lab.portal_v7_plan_nonces (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    agreement_id text NOT NULL CHECK (agreement_id ~ '^agreement_[A-Za-z0-9_-]{8,64}$'),
    nonce text NOT NULL CHECK (nonce ~ '^[A-Za-z0-9_-]{16,96}$'),
    expires_at bigint NOT NULL CHECK (expires_at > 0),
    claimed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    source text NOT NULL DEFAULT 'portal_v7_plan_review_lab'
        CHECK (source = 'portal_v7_plan_review_lab'),
    PRIMARY KEY (tenant_id, agreement_id, nonce)
);

ALTER TABLE perf_fin_role_lab.portal_v7_plan_nonces ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.portal_v7_plan_nonces FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_session_portal_v7_plan_nonce
    ON perf_fin_role_lab.portal_v7_plan_nonces
    FOR ALL
    USING (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session())
    WITH CHECK (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session());

REVOKE ALL ON perf_fin_role_lab.portal_v7_plan_nonces FROM PUBLIC;
-- CI will give only SELECT+INSERT to authenticated tenant logins.
-- No UPDATE, DELETE, TRUNCATE, CREATE or role impersonation; no pruning policy
-- or customer acceptance record. Restore must keep this table.
