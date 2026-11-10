-- F129: disposable PostgreSQL 16 laboratory only; never apply as a production migration.
-- Must be run as the isolated CI database administrator after creating two non-privileged roles.
BEGIN;
CREATE SCHEMA perf_f129_lab;
REVOKE ALL ON SCHEMA perf_f129_lab FROM PUBLIC;

CREATE TABLE perf_f129_lab.circuit (
    singleton integer PRIMARY KEY DEFAULT 1 CHECK (singleton = 1),
    quarantined boolean NOT NULL DEFAULT false,
    changed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
INSERT INTO perf_f129_lab.circuit (singleton) VALUES (1);

CREATE TABLE perf_f129_lab.receipts (
    operation_id text PRIMARY KEY CHECK (operation_id ~ '^[A-Za-z0-9_-]{8,128}$'),
    tenant_ref text NOT NULL CHECK (tenant_ref ~ '^[A-Za-z0-9_-]{8,128}$'),
    fingerprint text NOT NULL UNIQUE CHECK (fingerprint ~ '^[a-f0-9]{64}$'),
    epoch bigint NOT NULL CHECK (epoch >= 1),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

CREATE TABLE perf_f129_lab.uncertainty (
    operation_id text PRIMARY KEY CHECK (operation_id ~ '^[A-Za-z0-9_-]{8,128}$'),
    fingerprint text NOT NULL CHECK (fingerprint ~ '^[a-f0-9]{64}$'),
    reason text NOT NULL CHECK (reason IN ('timeout', 'disconnect', 'unknown_receipt', 'contradiction')),
    created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

-- No table DML privileges are given to any runtime principal. All mutation is via
-- carefully scoped SECURITY DEFINER functions owned by the CI database administrator.
CREATE FUNCTION perf_f129_lab.record_intent(
    p_operation text, p_tenant text, p_fingerprint text, p_epoch bigint
) RETURNS boolean
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, perf_f129_lab
AS $f129$
DECLARE
    locked_quarantine boolean;
    inserted_count integer;
BEGIN
    IF p_operation IS NULL OR p_operation !~ '^[A-Za-z0-9_-]{8,128}$'
       OR p_tenant IS NULL OR p_tenant !~ '^[A-Za-z0-9_-]{8,128}$'
       OR p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$'
       OR p_epoch IS NULL OR p_epoch < 1 THEN
        RETURN false;
    END IF;
    -- Serialize with quarantine; INSERT never races past the global fence.
    SELECT quarantined INTO locked_quarantine
      FROM perf_f129_lab.circuit WHERE singleton = 1 FOR UPDATE;
    IF locked_quarantine IS DISTINCT FROM false THEN
        RETURN false;
    END IF;
    INSERT INTO perf_f129_lab.receipts (operation_id, tenant_ref, fingerprint, epoch)
       VALUES (p_operation, p_tenant, p_fingerprint, p_epoch)
       ON CONFLICT DO NOTHING;
    GET DIAGNOSTICS inserted_count = ROW_COUNT;
    RETURN inserted_count = 1;
END;
$f129$;

CREATE FUNCTION perf_f129_lab.mark_uncertain(
    p_operation text, p_fingerprint text, p_reason text
) RETURNS boolean
LANGUAGE plpgsql SECURITY DEFINER SET search_path = pg_catalog, perf_f129_lab
AS $f129$
BEGIN
    IF p_operation IS NULL OR p_operation !~ '^[A-Za-z0-9_-]{8,128}$'
       OR p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$'
       OR p_reason IS NULL
       OR p_reason NOT IN ('timeout', 'disconnect', 'unknown_receipt', 'contradiction') THEN
        RETURN false;
    END IF;
    -- Unknown, late, or conflicting witnesses are never an excuse to reopen.
    -- This global fence is sticky: no SQL function for clearing it is installed.
    PERFORM 1 FROM perf_f129_lab.circuit WHERE singleton = 1 FOR UPDATE;
    UPDATE perf_f129_lab.circuit
      SET quarantined = true, changed_at = clock_timestamp()
      WHERE singleton = 1 AND quarantined = false;
    INSERT INTO perf_f129_lab.uncertainty (operation_id, fingerprint, reason)
      VALUES (p_operation, p_fingerprint, p_reason)
      ON CONFLICT DO NOTHING;
    RETURN false; -- never conveys authorization
END;
$f129$;

CREATE FUNCTION perf_f129_lab.snapshot()
RETURNS jsonb
LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, perf_f129_lab
AS $f129$
  SELECT jsonb_build_object(
    'quarantined', (SELECT quarantined FROM perf_f129_lab.circuit WHERE singleton=1),
    'receipts', (SELECT count(*) FROM perf_f129_lab.receipts),
    'uncertain_events', (SELECT count(*) FROM perf_f129_lab.uncertainty),
    'action_authorized', false
  );
$f129$;

REVOKE ALL ON ALL TABLES IN SCHEMA perf_f129_lab FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f129_lab FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f129_lab TO perf_f129_writer, perf_f129_failure_simulator;
GRANT EXECUTE ON FUNCTION perf_f129_lab.record_intent(text,text,text,bigint)
  TO perf_f129_writer;
GRANT EXECUTE ON FUNCTION perf_f129_lab.mark_uncertain(text,text,text)
  TO perf_f129_failure_simulator;
GRANT EXECUTE ON FUNCTION perf_f129_lab.snapshot()
  TO perf_f129_writer, perf_f129_failure_simulator;
COMMIT;
