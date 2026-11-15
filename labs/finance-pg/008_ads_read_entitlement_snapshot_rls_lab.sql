-- PostgreSQL 18 disposable CI ONLY. Apply after 005_v6_session_role_isolation_lab.sql.
-- Synthetic entitlement state is seeded/mutated solely by the PostgreSQL CI
-- administrator. Per-tenant authenticated login roles receive SELECT only.
-- This is NOT Portal IAM, real consent registration or a production migration.
CREATE TABLE perf_fin_role_lab.ads_read_entitlement_snapshots (
  tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
  agreement_id text NOT NULL CHECK (agreement_id ~ '^agreement_[A-Za-z0-9_-]{8,64}$'),
  period text NOT NULL CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'),
  provider text NOT NULL CHECK (provider = 'chatgpt_ads'),
  account_id text NOT NULL CHECK (account_id ~ '^adacct_[A-Za-z0-9]{3,64}$'),
  credential_reference text NOT NULL CHECK (credential_reference ~ '^ref_[A-Za-z0-9_-]{12,96}$'),
  consent_reference text NOT NULL CHECK (consent_reference ~ '^consent_[A-Za-z0-9_-]{8,64}$'),
  agreement_status text NOT NULL CHECK (agreement_status IN ('synthetic_active','synthetic_terminated')),
  agreement_valid_from bigint NOT NULL CHECK (agreement_valid_from > 0),
  agreement_valid_until bigint NOT NULL CHECK (agreement_valid_until > agreement_valid_from),
  account_status text NOT NULL CHECK (account_status IN ('synthetic_active','synthetic_disconnected')),
  owner_tenant_id text NOT NULL CHECK (owner_tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
  consent_status text NOT NULL CHECK (consent_status IN ('synthetic_granted','synthetic_revoked')),
  consent_granted_at bigint NOT NULL CHECK (consent_granted_at > 0),
  consent_expires_at bigint NOT NULL CHECK (consent_expires_at > consent_granted_at),
  consent_revoked_at bigint NULL CHECK (consent_revoked_at IS NULL OR consent_revoked_at > 0),
  permission text NOT NULL CHECK (permission IN ('ads.insights.read','ads.campaigns.write')),
  snapshot_version bigint NOT NULL CHECK (snapshot_version >= 1),
  changed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
  PRIMARY KEY (tenant_id, agreement_id, period, account_id)
);

ALTER TABLE perf_fin_role_lab.ads_read_entitlement_snapshots ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.ads_read_entitlement_snapshots FORCE ROW LEVEL SECURITY;
CREATE POLICY tenant_login_select_ads_entitlement
  ON perf_fin_role_lab.ads_read_entitlement_snapshots FOR SELECT
  USING (tenant_id = perf_fin_role_lab.tenant_for_authenticated_session());

REVOKE ALL ON perf_fin_role_lab.ads_read_entitlement_snapshots FROM PUBLIC;
-- The workflow GRANTs SELECT only to two test login roles after 005.
-- Only CI postgres admin updates/revokes; tenant roles cannot INSERT/UPDATE/
-- DELETE, bypass FORCE RLS or alter login_tenant_registry.
