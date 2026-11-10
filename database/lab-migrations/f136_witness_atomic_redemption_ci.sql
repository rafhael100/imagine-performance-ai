-- F136 ONLY for two disposable PostgreSQL16 databases inside GitHub Actions.
-- Apply to witness after F130/F132/F134, with CI-only roles created by runner.
BEGIN;
CREATE SCHEMA perf_f136_witness;
REVOKE ALL ON SCHEMA perf_f136_witness FROM PUBLIC;

CREATE TABLE perf_f136_witness.issued (
  fingerprint text PRIMARY KEY CHECK (fingerprint ~ '^[a-f0-9]{64}$'),
  operation_id text NOT NULL CHECK (operation_id ~ '^[A-Za-z0-9_-]{8,128}$'),
  tenant_ref text NOT NULL CHECK (tenant_ref ~ '^[A-Za-z0-9_-]{8,128}$'),
  generation bigint NOT NULL CHECK (generation >= 1),
  issued_at bigint NOT NULL CHECK (issued_at > 0),
  signature text NOT NULL CHECK (signature ~ '^[a-f0-9]{64}$'),
  redeemed boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);

CREATE FUNCTION perf_f136_witness.issue_once(
 p_operation text, p_tenant text, p_fingerprint text, p_generation bigint
) RETURNS TABLE(issued_at bigint,signature text)
LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f136_witness
AS $f136$
DECLARE v_time bigint; v_sig text;
BEGIN
  -- F134 signer consumes the F132 witness nonce. Inserts are in the SAME
  -- witness transaction; failed insert rolls back reservation and signature.
  SELECT i.issued_at,i.signature INTO v_time,v_sig
    FROM perf_f134_witness.issue_permit(
      p_operation,p_tenant,p_fingerprint,p_generation) AS i;
  IF v_time IS NULL OR v_sig IS NULL THEN RETURN; END IF;
  INSERT INTO perf_f136_witness.issued
   (fingerprint,operation_id,tenant_ref,generation,issued_at,signature)
  VALUES (p_fingerprint,p_operation,p_tenant,p_generation,v_time,v_sig);
  RETURN QUERY SELECT v_time,v_sig;
END;
$f136$;

CREATE FUNCTION perf_f136_witness.guard_redemption()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f136_witness
AS $f136$
DECLARE v_epoch bigint;
BEGIN
  IF OLD.redeemed OR NEW.redeemed IS DISTINCT FROM TRUE
    OR NEW.fingerprint IS DISTINCT FROM OLD.fingerprint
    OR NEW.operation_id IS DISTINCT FROM OLD.operation_id
    OR NEW.tenant_ref IS DISTINCT FROM OLD.tenant_ref
    OR NEW.generation IS DISTINCT FROM OLD.generation
    OR NEW.issued_at IS DISTINCT FROM OLD.issued_at
    OR NEW.signature IS DISTINCT FROM OLD.signature
    OR NEW.created_at IS DISTINCT FROM OLD.created_at THEN
      RAISE EXCEPTION 'F136 witness redemption is append-only and irreversible'
        USING ERRCODE='P0001';
  END IF;
  -- Lock the external epoch row until the remote transaction COMMIT so a
  -- concurrent floor raise cannot interleave with this redemption.
  SELECT generation INTO v_epoch FROM perf_f132_witness.epoch_floor
    WHERE singleton=1 FOR SHARE;
  IF v_epoch IS NULL OR OLD.generation <> v_epoch THEN
    RAISE EXCEPTION 'F136 stale or unknown witness epoch'
      USING ERRCODE='P0001';
  END IF;
  RETURN NEW;
END;
$f136$;

CREATE TRIGGER perf_f136_guard_redemption
  BEFORE UPDATE ON perf_f136_witness.issued
  FOR EACH ROW EXECUTE FUNCTION perf_f136_witness.guard_redemption();

-- Former F134 caller is cut off; one CI role can mint, a separate CI role
-- can only view and atomically mark existing witness permits redeemed.
REVOKE EXECUTE ON FUNCTION perf_f134_witness.issue_permit(text,text,text,bigint)
  FROM perf_f134_witness_bridge;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f136_witness FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f136_witness FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f136_witness TO perf_f136_issuer,perf_f136_redeemer;
GRANT EXECUTE ON FUNCTION perf_f136_witness.issue_once(text,text,text,bigint)
  TO perf_f136_issuer;
GRANT SELECT ON perf_f136_witness.issued TO perf_f136_redeemer;
GRANT UPDATE (redeemed) ON perf_f136_witness.issued TO perf_f136_redeemer;
COMMIT;
