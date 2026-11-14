-- Performance AI PostgreSQL 18 synthetic finance laboratory only. Never apply to production.
BEGIN;
CREATE SCHEMA perf_fin_lab;
CREATE ROLE perf_fin_lab_app NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
GRANT USAGE ON SCHEMA perf_fin_lab TO perf_fin_lab_app;

CREATE TABLE perf_fin_lab.spend_evidence (
    tenant_id uuid NOT NULL,
    reference_month date NOT NULL,
    channel text NOT NULL,
    report_id text NOT NULL,
    spend_cents bigint NOT NULL,
    record_sha256 text NOT NULL,
    evidence_source text NOT NULL DEFAULT 'synthetic_fixture',
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, reference_month, channel, report_id),
    CONSTRAINT monthly_boundary CHECK (extract(day FROM reference_month) = 1),
    CONSTRAINT channel_supported CHECK (channel IN ('google_ads', 'meta_ads')),
    CONSTRAINT report_id_format CHECK (report_id ~ '^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$'),
    CONSTRAINT spend_bounds CHECK (spend_cents BETWEEN 0 AND 100000000),
    CONSTRAINT record_sha256_format CHECK (record_sha256 ~ '^[a-f0-9]{64}$'),
    CONSTRAINT synthetic_source_only CHECK (evidence_source = 'synthetic_fixture')
);
CREATE TABLE perf_fin_lab.monthly_review_candidate (
    tenant_id uuid NOT NULL,
    reference_month date NOT NULL,
    quote_sha256 text NOT NULL,
    evidence_sha256 text NOT NULL,
    advance_fee_cents bigint NOT NULL,
    earned_fee_cents bigint NOT NULL,
    adjustment_cents bigint NOT NULL,
    review_state text NOT NULL DEFAULT 'requires_finance_review',
    finance_approved boolean NOT NULL DEFAULT false,
    gateway_write_enabled boolean NOT NULL DEFAULT false,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (tenant_id, reference_month, quote_sha256, evidence_sha256),
    CONSTRAINT review_month_boundary CHECK (extract(day FROM reference_month) = 1),
    CONSTRAINT review_quote_digest CHECK (quote_sha256 ~ '^[a-f0-9]{64}$'),
    CONSTRAINT review_evidence_digest CHECK (evidence_sha256 ~ '^[a-f0-9]{64}$'),
    CONSTRAINT review_advance_bounds CHECK (advance_fee_cents BETWEEN 0 AND 40000000),
    CONSTRAINT review_earned_bounds CHECK (earned_fee_cents BETWEEN 0 AND 40000000),
    CONSTRAINT review_arithmetic CHECK (adjustment_cents = earned_fee_cents - advance_fee_cents),
    CONSTRAINT review_state_blocked CHECK (review_state = 'requires_finance_review'),
    CONSTRAINT review_no_approval CHECK (finance_approved = false),
    CONSTRAINT review_no_gateway CHECK (gateway_write_enabled = false)
);
ALTER TABLE perf_fin_lab.spend_evidence ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_lab.spend_evidence FORCE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_lab.monthly_review_candidate ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_fin_lab.monthly_review_candidate FORCE ROW LEVEL SECURITY;
-- The caller-controlled GUC is NOT authentication: trusted middleware is required before production.
CREATE POLICY read_spend_tenant ON perf_fin_lab.spend_evidence
  FOR SELECT TO perf_fin_lab_app USING (tenant_id::text = current_setting('perf_lab.tenant_id', true));
CREATE POLICY insert_spend_tenant ON perf_fin_lab.spend_evidence
  FOR INSERT TO perf_fin_lab_app WITH CHECK (tenant_id::text = current_setting('perf_lab.tenant_id', true));
CREATE POLICY read_review_tenant ON perf_fin_lab.monthly_review_candidate
  FOR SELECT TO perf_fin_lab_app USING (tenant_id::text = current_setting('perf_lab.tenant_id', true));
CREATE POLICY insert_review_tenant ON perf_fin_lab.monthly_review_candidate
  FOR INSERT TO perf_fin_lab_app WITH CHECK (tenant_id::text = current_setting('perf_lab.tenant_id', true));
GRANT SELECT, INSERT ON perf_fin_lab.spend_evidence TO perf_fin_lab_app;
GRANT SELECT, INSERT ON perf_fin_lab.monthly_review_candidate TO perf_fin_lab_app;
COMMIT;
