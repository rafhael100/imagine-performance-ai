-- PostgreSQL 16 sandbox only. Lab draft records are NOT invoices, credits or approvals.
CREATE TABLE IF NOT EXISTS perf_fin_lab.reconciliation_drafts (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    close_id text NOT NULL CHECK (close_id ~ '^[a-f0-9]{64}$'),
    period text NOT NULL CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'),
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
    status text NOT NULL DEFAULT 'lab_draft_unverified_spend'
        CHECK (status = 'lab_draft_unverified_spend'),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id, close_id),
    UNIQUE (tenant_id, period, quote_claim_sha256),
    -- Lab-only fail closed: there is no approved correction or revision policy.
    CONSTRAINT one_synthetic_close_per_month UNIQUE (tenant_id, period),
    CHECK (adjustment_cents = unverified_earned_fee_cents - advance_fee_cents)
);
-- Deliberately immutable: runtime role receives only SELECT and INSERT.
ALTER TABLE perf_fin_lab.reconciliation_drafts ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_lab.reconciliation_drafts FORCE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_drafts ON perf_fin_lab.reconciliation_drafts;
CREATE POLICY tenant_drafts ON perf_fin_lab.reconciliation_drafts
  FOR ALL
  USING (tenant_id = current_setting('app.tenant_id', true))
  WITH CHECK (tenant_id = current_setting('app.tenant_id', true));
