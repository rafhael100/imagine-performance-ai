-- PostgreSQL 18 CI ONLY: V6 signed agreement identity, NO real contracts/charges.
-- Agreement ID is in the genuine Portal HMAC V6, unlike v5's external binding.
-- Still NOT production: custom app.tenant_id GUC can be forged by SQL clients.
CREATE SCHEMA IF NOT EXISTS perf_fin_v6_lab;
CREATE TABLE perf_fin_v6_lab.agreement_close_drafts (
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
ALTER TABLE perf_fin_v6_lab.agreement_close_drafts ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_v6_lab.agreement_close_drafts FORCE ROW LEVEL SECURITY;
CREATE POLICY v6_synthetic_agreement_scope ON perf_fin_v6_lab.agreement_close_drafts
  FOR ALL
  USING (tenant_id = current_setting('app.tenant_id', true))
  WITH CHECK (tenant_id = current_setting('app.tenant_id', true));

-- Role perf_fin_lab_app gets ONLY SELECT and INSERT, never UPDATE, DELETE,
-- TRUNCATE or schema CREATE; disposable database only.
