-- ISOLATED POSTGRESQL 18 CI ONLY. Not a production migration or Portal checkout.
-- Important: app.tenant_id custom GUC is forgeable by any SQL-using principal.
-- Multiagreement IDs are trusted ONLY because fixture bindings are provisioned
-- by the CI owner; these are not signatures of real business agreements.
CREATE SCHEMA IF NOT EXISTS perf_fin_multi_lab;

-- Provisioned by a distinct trusted CI fixture owner, not by runtime app role.
CREATE TABLE perf_fin_multi_lab.agreement_quote_bindings (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    agreement_id text NOT NULL CHECK (agreement_id ~ '^agreement_[A-Za-z0-9_-]{8,64}$'),
    period text NOT NULL CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'),
    catalog_sha256 text NOT NULL CHECK (catalog_sha256 ~ '^[a-f0-9]{64}$'),
    quote_claim_sha256 text NOT NULL CHECK (quote_claim_sha256 ~ '^[a-f0-9]{64}$'),
    is_synthetic boolean NOT NULL DEFAULT true CHECK (is_synthetic = true),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id, agreement_id, period),
    UNIQUE (tenant_id, quote_claim_sha256)
);

-- A single agreement can have one immutable closed synthetic draft per month.
-- Two different agreements of ONE tenant CAN each close in the same month.
CREATE TABLE perf_fin_multi_lab.agreement_close_drafts (
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
    FOREIGN KEY (tenant_id, agreement_id, period)
      REFERENCES perf_fin_multi_lab.agreement_quote_bindings (tenant_id, agreement_id, period),
    CHECK (adjustment_cents = unverified_earned_fee_cents - advance_fee_cents)
);

ALTER TABLE perf_fin_multi_lab.agreement_quote_bindings ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_multi_lab.agreement_quote_bindings FORCE ROW LEVEL SECURITY;
CREATE POLICY synthetic_binding_scope ON perf_fin_multi_lab.agreement_quote_bindings
  FOR ALL
  USING (tenant_id = current_setting('app.tenant_id', true))
  WITH CHECK (tenant_id = current_setting('app.tenant_id', true));

ALTER TABLE perf_fin_multi_lab.agreement_close_drafts ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_multi_lab.agreement_close_drafts FORCE ROW LEVEL SECURITY;
CREATE POLICY synthetic_multi_draft_scope ON perf_fin_multi_lab.agreement_close_drafts
  FOR ALL
  USING (tenant_id = current_setting('app.tenant_id', true))
  WITH CHECK (tenant_id = current_setting('app.tenant_id', true));
-- Runtime role is granted SELECT binding and SELECT/INSERT draft ONLY.
-- No UPDATE, DELETE, TRUNCATE, REFERENCES or schema CREATE.
