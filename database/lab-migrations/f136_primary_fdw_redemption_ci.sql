-- F136 disposable CI ONLY. postgres_fdw carries the final mutation to the
-- witness database during the same local transaction; it is NOT 2PC.
BEGIN;
CREATE EXTENSION IF NOT EXISTS postgres_fdw;
CREATE SCHEMA perf_f136_primary;
REVOKE ALL ON SCHEMA perf_f136_primary FROM PUBLIC;

CREATE SERVER perf_f136_witness_ci FOREIGN DATA WRAPPER postgres_fdw
OPTIONS(host '127.0.0.1',port '5433',dbname 'perf_f132_witness_ci');
-- Only the superuser/function owner has this mapping, not the F136 login.
CREATE USER MAPPING FOR postgres SERVER perf_f136_witness_ci
  OPTIONS(user 'perf_f136_redeemer', password :'redeemer_password');

CREATE FOREIGN TABLE perf_f136_primary.remote_issued (
  fingerprint text,
  operation_id text,
  tenant_ref text,
  generation bigint,
  issued_at bigint,
  signature text,
  redeemed boolean
) SERVER perf_f136_witness_ci
OPTIONS(schema_name 'perf_f136_witness',table_name 'issued');

CREATE FUNCTION perf_f136_primary.redeem_and_record(
 p_operation text,p_tenant text,p_fingerprint text,p_generation bigint,
 p_issued_at bigint,p_signature text
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f136_primary
AS $f136$
DECLARE v_claims integer;
BEGIN
 IF p_operation IS NULL OR p_operation !~ '^[A-Za-z0-9_-]{8,128}$'
    OR p_tenant IS NULL OR p_tenant !~ '^[A-Za-z0-9_-]{8,128}$'
    OR p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$'
    OR p_signature IS NULL OR p_signature !~ '^[a-f0-9]{64}$'
    OR p_generation IS NULL OR p_generation < 1
    OR p_issued_at IS NULL OR p_issued_at < 1 THEN
   RETURN false;
 END IF;

 -- Online witness check is a remote UPDATE, not a preflight SELECT. It
 -- transitions ONE matching, nonredeemed permit to redeemed and locks the
 -- witness epoch floor via the remote trigger. A replay returns zero rows.
 WITH claimed AS (
   UPDATE perf_f136_primary.remote_issued AS r SET redeemed=true
   WHERE r.fingerprint=p_fingerprint AND r.operation_id=p_operation
      AND r.tenant_ref=p_tenant AND r.generation=p_generation
      AND r.issued_at=p_issued_at AND r.signature=p_signature
      AND r.redeemed=false
   RETURNING r.fingerprint
 ) SELECT count(*) INTO v_claims FROM claimed;
 IF v_claims <> 1 THEN RETURN false; END IF;

 -- Existing HMAC, local epoch and F129 circuit checks remain mandatory.
 -- A failed primary check can burn the remote permit; never retry.
 RETURN perf_f134_primary.record_signed(
   p_operation,p_tenant,p_fingerprint,p_generation,p_issued_at,p_signature
 );
END;
$f136$;

-- Remove prior F134 direct writer permission and do not grant direct
-- FDW/table access to this new restricted SQL principal.
REVOKE EXECUTE ON FUNCTION perf_f134_primary.record_signed(
  text,text,text,bigint,bigint,text) FROM perf_f134_primary_bridge;
REVOKE ALL ON ALL FOREIGN TABLES IN SCHEMA perf_f136_primary FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f136_primary FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f136_primary TO perf_f136_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f136_primary.redeem_and_record(
  text,text,text,bigint,bigint,text) TO perf_f136_primary_bridge;
GRANT USAGE ON SCHEMA perf_f132_primary TO perf_f136_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.current_generation()
  TO perf_f136_primary_bridge;
COMMIT;
