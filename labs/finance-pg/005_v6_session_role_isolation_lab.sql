-- POSTGRESQL 18 THROWAWAY CI LAB ONLY. NEVER deploy this migration to VPS.
-- Threat model: callers can issue arbitrary SQL on their own login, including
-- SET app.tenant_id, but cannot become another authenticated PostgreSQL login.
-- session_user is the authenticated login, not a user-controlled custom GUC.
CREATE SCHEMA perf_fin_role_lab;

-- Seeded exclusively as CI's postgres owner. Runtime tenant roles get NO
-- SELECT/INSERT/UPDATE/DELETE on this table.
CREATE TABLE perf_fin_role_lab.login_tenant_registry (
    login_role name PRIMARY KEY,
    tenant_id text NOT NULL UNIQUE
        CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

-- session_user inside a SECURITY DEFINER function stays the ORIGINAL
-- authenticated PostgreSQL session login. This fixed search_path and
-- schema-qualified table are mandatory to avoid unsafe name resolution.
CREATE FUNCTION perf_fin_role_lab.tenant_for_authenticated_session()
RETURNS text
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, perf_fin_role_lab
AS $$
    SELECT tenant_id
      FROM perf_fin_role_lab.login_tenant_registry
     WHERE login_role = session_user::name
$$;

REVOKE ALL ON FUNCTION perf_fin_role_lab.tenant_for_authenticated_session() FROM PUBLIC;
-- No default PUBLIC permission on schema, no CREATE on tenant roles.
REVOKE ALL ON SCHEMA perf_fin_role_lab FROM PUBLIC;
REVOKE ALL ON perf_fin_role_lab.login_tenant_registry FROM PUBLIC;

CREATE TABLE perf_fin_role_lab.signed_quote_nonces (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    nonce text NOT NULL CHECK (nonce ~ '^[A-Za-z0-9_-]{16,96}$'),
    key_id text NOT NULL CHECK (key_id ~ '^k_[A-Za-z0-9_-]{4,64}$'),
    expires_at bigint NOT NULL CHECK (expires_at > 0),
    claimed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id, nonce)
);

CREATE TABLE perf_fin_role_lab.agreement_close_drafts (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    agreement_id text NOT NULL CHECK (agreement_id ~ '^agreement_[A-Za-z0-9_-]{8,64}$'),
    period text NOT NULL CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'),
    close_id text NOT NULL CHECK (close_id ~ '^[a-f0-9]{64}$'),
    catalog_sha256 text NOT NULL CHECK (catalog_sha256 ~ '^[a-f0-9]{64}$'),
    quote_claim_sha256 text NOT NULL CHECK (quote_claim_sha256 ~ '^[a-f0-9]{64}$'),
    spend_claim_sha256 text NOT NULL CHECK (spend_claim_sha256 ~ '^[a-f0-9]{64}$'),
    planned_media_cents bigint NOT NULL CHECK (planned_media_cents >= 0),
    unverified_actual_media_cents bigint NOT NULL CHECK (unverified_actual_media_cents >= 0),
    advance_fee_cents bigint NOT NULL CHECK (advance_fee_cents >= 0),
    unverified_earned_fee_cents bigint NOT NULL CHECK (unverified_earned_fee_cents >= 0),
    adjustment_cents bigint NOT NULL,
    source_verified boolean NOT NULL DEFAULT false CHECK (source_verified = false),
    finance_approved boolean NOT NULL DEFAULT false CHECK (finance_approved = false),
    billable boolean NOT NULL DEFAULT false CHECK (billable = false),
    status text NOT NULL DEFAULT 'unverified_multiagreement_lab_draft'
        CHECK (status = 'unverified_multiagreement_lab_draft'),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id, close_id),
    UNIQUE (tenant_id, agreement_id, period),
    UNIQUE (tenant_id, quote_claim_sha256),
    CHECK (adjustment_cents = unverified_earned_fee_cents - advance_fee_cents)
);

ALTER TABLE perf_fin_role_lab.signed_quote_nonces ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.signed_quote_nonces FORCE ROW LEVEL SECURITY;
CREATE POLICY session_owner_nonces ON perf_fin_role_lab.signed_quote_nonces
    FOR ALL
    USING (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session())
    WITH CHECK (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session());

ALTER TABLE perf_fin_role_lab.agreement_close_drafts ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.agreement_close_drafts FORCE ROW LEVEL SECURITY;
CREATE POLICY session_owner_agreements ON perf_fin_role_lab.agreement_close_drafts
    FOR ALL
    USING (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session())
    WITH CHECK (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session());

-- The workflow creates two LOGIN roles with no membership to each other.
-- GRANT only schema USAGE, mapper EXECUTE and the required table SELECT/INSERT.
-- No UPDATE, DELETE, TRUNCATE, REFERENCES, schema CREATE or role inheritance.
