-- F143 CI ONLY: SQL-side oracle one-time redemption before F142 HMAC.
BEGIN;
CREATE SCHEMA perf_f143_primary;
REVOKE ALL ON SCHEMA perf_f143_primary FROM PUBLIC;
CREATE SERVER perf_f143_oracle_ci FOREIGN DATA WRAPPER postgres_fdw
OPTIONS(host 'oracle',port '5432',dbname 'perf_f143_oracle_ci');
CREATE USER MAPPING FOR postgres SERVER perf_f143_oracle_ci
OPTIONS(user 'perf_f143_oracle_redeemer',password :'oracle_redeemer_password');
CREATE FOREIGN TABLE perf_f143_primary.remote_permits(
 fingerprint text,
 operation_id text,
 tenant_ref text,
 generation bigint,
 journal_issued_at bigint,
 journal_sequence bigint,
 journal_head text,
 journal_mac text,
 redeemed boolean
) SERVER perf_f143_oracle_ci
OPTIONS(schema_name 'perf_f143_oracle',table_name 'permits');

CREATE FUNCTION perf_f143_primary.redeem_online_oracle(
 p_op text,p_tenant text,p_fp text,p_epoch bigint,
 p_wtime bigint,p_wsig text,
 p_jtime bigint,p_seq bigint,p_head text,p_mac text
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f143_primary
AS $f143$
DECLARE v_claims bigint;
BEGIN
 IF p_op IS NULL OR p_op !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_tenant IS NULL OR p_tenant !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_fp IS NULL OR p_fp !~ '^[a-f0-9]{64}$'
 OR p_epoch IS NULL OR p_epoch<1
 OR p_jtime IS NULL OR p_seq IS NULL OR p_seq<2
 OR p_head IS NULL OR p_head !~ '^[a-f0-9]{64}$'
 OR p_mac IS NULL OR p_mac !~ '^[a-f0-9]{64}$' THEN RETURN false; END IF;

 -- This UPDATE is a remote, atomic "consume if still unspent" decision.
 -- It is NOT the old F142 local-only verification. The oracle keeps the
 -- redemption if the first three SQL databases are later restored.
 WITH claimed AS (
  UPDATE perf_f143_primary.remote_permits AS r SET redeemed=true
   WHERE r.fingerprint=p_fp AND r.operation_id=p_op
    AND r.tenant_ref=p_tenant AND r.generation=p_epoch
    AND r.journal_issued_at=p_jtime AND r.journal_sequence=p_seq
    AND r.journal_head=p_head AND r.journal_mac=p_mac
    AND r.redeemed=false
  RETURNING r.fingerprint
 ) SELECT count(*) INTO v_claims FROM claimed;
 IF v_claims<>1 THEN RETURN false; END IF;

 RETURN perf_f142_primary.record_with_journal_attestation(
  p_op,p_tenant,p_fp,p_epoch,p_wtime,p_wsig,
  p_jtime,p_seq,p_head,p_mac
 );
END;
$f143$;

REVOKE EXECUTE ON FUNCTION perf_f142_primary.record_with_journal_attestation(
 text,text,text,bigint,bigint,text,bigint,bigint,text,text)
 FROM perf_f142_primary_bridge;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f143_primary FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f143_primary FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f143_primary TO perf_f143_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f143_primary.redeem_online_oracle(
 text,text,text,bigint,bigint,text,bigint,bigint,text,text)
 TO perf_f143_primary_bridge;
GRANT USAGE ON SCHEMA perf_f132_primary TO perf_f143_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.current_generation()
 TO perf_f143_primary_bridge;
COMMIT;
