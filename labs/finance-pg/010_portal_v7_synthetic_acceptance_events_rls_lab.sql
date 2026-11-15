-- Disposable PostgreSQL 18 CI only. Run AFTER 005 + 009.
-- Synthetic user acceptance is admin-seeded; it is NOT an authenticated
-- customer approval, production IAM record or consent lawful basis.
CREATE TABLE perf_fin_role_lab.portal_v7_acceptance_events (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    agreement_id text NOT NULL CHECK (agreement_id ~ '^agreement_[A-Za-z0-9_-]{8,64}$'),
    period text NOT NULL CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'),
    event_revision integer NOT NULL CHECK (event_revision > 0),
    state text NOT NULL CHECK (state IN ('synthetic_accepted','synthetic_revoked')),
    plan_sha256 text NOT NULL CHECK (plan_sha256 ~ '^[a-f0-9]{64}$'),
    terms_sha256 text NOT NULL CHECK (terms_sha256 ~ '^[a-f0-9]{64}$'),
    synthetic_actor_ref text NOT NULL CHECK (synthetic_actor_ref ~ '^actor_lab_[A-Za-z0-9_-]{8,64}$'),
    mfa_snapshot_verified boolean NOT NULL DEFAULT false,
    full_month_reviewed boolean NOT NULL DEFAULT false,
    event_at bigint NOT NULL CHECK (event_at > 0),
    expires_at bigint NOT NULL CHECK (expires_at > event_at),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id,agreement_id,period,event_revision),
    CHECK (state <> 'synthetic_accepted' OR (mfa_snapshot_verified AND full_month_reviewed))
);

ALTER TABLE perf_fin_role_lab.portal_v7_acceptance_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.portal_v7_acceptance_events FORCE ROW LEVEL SECURITY;

CREATE POLICY tenant_login_read_v7_acceptance
    ON perf_fin_role_lab.portal_v7_acceptance_events
    FOR SELECT
    USING (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session());

REVOKE ALL ON perf_fin_role_lab.portal_v7_acceptance_events FROM PUBLIC;
-- CI tester grants SELECT ONLY to tenant login roles.
-- Only postgres admin seeds synthetic acceptance and revocations; no UPDATE
-- or DELETE to any login, so historical entries are append-only in CI.
-- A later privileged event with a greater revision supersedes earlier ones.
-- A successful check is joined to a nonce insert in ONE SQL statement;
-- this alone does not prevent revocation after the statement commits.
