-- PostgreSQL 18 synthetic correction-review events, no real finance ledger.
-- Requires 005_v6_session_role_isolation_lab.sql. NEVER deploy to a VPS.
-- A revision is one immutable new state of SYNTHETIC reported media spend.
-- It is not an invoice, credit, approved adjustment or authoritative ad spend.
CREATE TABLE perf_fin_role_lab.spend_correction_events (
    tenant_id text NOT NULL CHECK (tenant_id ~ '^tenant_[A-Za-z0-9_-]{8,64}$'),
    agreement_id text NOT NULL CHECK (agreement_id ~ '^agreement_[A-Za-z0-9_-]{8,64}$'),
    period text NOT NULL CHECK (period ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'),
    revision_index integer NOT NULL CHECK (revision_index BETWEEN 1 AND 100),
    base_close_id text NOT NULL CHECK (base_close_id ~ '^[a-f0-9]{64}$'),
    quote_claim_sha256 text NOT NULL CHECK (quote_claim_sha256 ~ '^[a-f0-9]{64}$'),
    previous_event_sha256 text NOT NULL CHECK (previous_event_sha256 ~ '^[a-f0-9]{64}$'),
    event_sha256 text NOT NULL CHECK (event_sha256 ~ '^[a-f0-9]{64}$'),
    new_spend_claim_sha256 text NOT NULL CHECK (new_spend_claim_sha256 ~ '^[a-f0-9]{64}$'),
    unverified_new_spend_cents bigint NOT NULL CHECK (unverified_new_spend_cents >= 0),
    unverified_new_earned_fee_cents bigint NOT NULL CHECK (unverified_new_earned_fee_cents >= 0),
    delta_earned_fee_cents bigint NOT NULL,
    note_code text NOT NULL CHECK
      (note_code IN ('late_spend_fixture','account_allocation_fixture')),
    source_verified boolean NOT NULL DEFAULT false CHECK (source_verified=false),
    finance_approved boolean NOT NULL DEFAULT false CHECK (finance_approved=false),
    billable boolean NOT NULL DEFAULT false CHECK (billable=false),
    asaas_write_enabled boolean NOT NULL DEFAULT false CHECK (asaas_write_enabled=false),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY (tenant_id,agreement_id,period,revision_index),
    UNIQUE (tenant_id,event_sha256),
    FOREIGN KEY (tenant_id,base_close_id)
      REFERENCES perf_fin_role_lab.agreement_close_drafts (tenant_id,close_id)
);
ALTER TABLE perf_fin_role_lab.spend_correction_events ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_role_lab.spend_correction_events FORCE ROW LEVEL SECURITY;
CREATE POLICY session_owner_corrections
  ON perf_fin_role_lab.spend_correction_events
  FOR ALL
  USING (tenant_id=perf_fin_role_lab.tenant_for_authenticated_session())
  WITH CHECK (tenant_id=perf_fin_role_lab.tenant_for_authenticated_session());
-- Runtime role: SELECT, INSERT only. No UPDATE, DELETE, TRUNCATE or CREATE.
-- Concurrency: PK(revision_index) ensures at most one winner for a given
-- revision; an outdated expected revision or competing writer fails closed.
