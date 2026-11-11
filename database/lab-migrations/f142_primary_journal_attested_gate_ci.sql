-- F142 disposable CI only. A restricted application role CANNOT call F139
-- directly. F142 requires a short-lived, journal-bound HMAC attestation.
-- HMAC is checked SQL-side but is NOT an independent offsite authority:
-- superusers and restore of all three SQL databases can bypass/replay.
BEGIN;
CREATE SCHEMA perf_f142_primary;
REVOKE ALL ON SCHEMA perf_f142_primary FROM PUBLIC;

CREATE TABLE perf_f142_primary.test_mac_key (
 singleton integer PRIMARY KEY CHECK(singleton=1),
 key_bytes bytea NOT NULL CHECK(octet_length(key_bytes)=32)
);
INSERT INTO perf_f142_primary.test_mac_key(singleton,key_bytes)
 VALUES(1,decode(:'f142_mac_key','hex'));

CREATE FUNCTION perf_f142_primary.record_with_journal_attestation(
 p_operation text,p_tenant text,p_fingerprint text,p_generation bigint,
 p_witness_issued_at bigint,p_witness_signature text,
 p_journal_issued_at bigint,p_journal_sequence bigint,
 p_journal_head text,p_journal_mac text
) RETURNS boolean
LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f142_primary
AS $f142$
DECLARE
 v_key bytea;
 v_expected text;
 v_message text;
 v_now bigint;
BEGIN
 IF p_operation IS NULL OR p_operation !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_tenant IS NULL OR p_tenant !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$'
 OR p_generation IS NULL OR p_generation<1
 OR p_witness_issued_at IS NULL OR p_witness_signature IS NULL
 OR p_journal_issued_at IS NULL OR p_journal_sequence IS NULL
 OR p_journal_sequence<2
 OR p_journal_head IS NULL OR p_journal_head !~ '^[a-f0-9]{64}$'
 OR p_journal_mac IS NULL OR p_journal_mac !~ '^[a-f0-9]{64}$' THEN
   RETURN false;
 END IF;
 v_now := floor(extract(epoch from clock_timestamp()))::bigint;
 IF p_journal_issued_at < v_now - 30 OR p_journal_issued_at > v_now+5 THEN
   RETURN false;
 END IF;
 SELECT key_bytes INTO STRICT v_key
  FROM perf_f142_primary.test_mac_key WHERE singleton=1;
 v_message := format('F142-v1|%s|%s|%s|%s|%s|%s|%s',
  p_operation,p_tenant,p_fingerprint,p_generation,
  p_journal_issued_at,p_journal_sequence,p_journal_head);
 v_expected := encode(public.hmac(convert_to(v_message,'UTF8'),v_key,'sha256'),'hex');
 IF v_expected<>p_journal_mac THEN RETURN false; END IF;

 -- All previous local epoch, third-anchor FOR SHARE, witness redemption,
 -- witness HMAC and F129 journal constraints remain in effect.
 RETURN perf_f139_primary.record_with_locked_anchor(
  p_operation,p_tenant,p_fingerprint,p_generation,
  p_witness_issued_at,p_witness_signature
 );
END;
$f142$;

REVOKE EXECUTE ON FUNCTION perf_f139_primary.record_with_locked_anchor(
 text,text,text,bigint,bigint,text) FROM perf_f139_primary_bridge;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f142_primary FROM PUBLIC;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f142_primary FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f142_primary TO perf_f142_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f142_primary.record_with_journal_attestation(
 text,text,text,bigint,bigint,text,bigint,bigint,text,text)
 TO perf_f142_primary_bridge;
GRANT USAGE ON SCHEMA perf_f132_primary TO perf_f142_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.current_generation()
 TO perf_f142_primary_bridge;
COMMIT;
