-- F139: disposable PostgreSQL16 CI ONLY.
-- postgres_fdw row-mark checks the third anchor with FOR SHARE inside the
-- calling primary transaction; this is NOT a distributed 2PC or offsite anchor.
BEGIN;
CREATE SCHEMA perf_f139_primary;
REVOKE ALL ON SCHEMA perf_f139_primary FROM PUBLIC;

CREATE FUNCTION perf_f139_primary.record_with_locked_anchor(
 p_operation text,p_tenant text,p_fingerprint text,p_generation bigint,
 p_issued_at bigint,p_signature text
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f139_primary
AS $f139$
DECLARE
 v_anchor_generation bigint;
BEGIN
 IF p_generation IS NULL OR p_generation < 1 THEN RETURN false; END IF;

 -- postgres_fdw propagates the row-mark to the remote authority.
 -- Lock is held by its remote transaction until the local transaction closes.
 -- Unlike F138's ordinary SELECT, floor advancement cannot interleave
 -- with THIS registration while the local transaction is still open.
 SELECT a.generation INTO v_anchor_generation
   FROM perf_f138_primary.external_epoch AS a
   WHERE a.singleton=1 FOR SHARE;
 IF v_anchor_generation IS NULL
    OR v_anchor_generation <> p_generation THEN RETURN false; END IF;

 -- Existing F136 remote redemption and HMAC/local-epoch/circuit checks.
 RETURN perf_f136_primary.redeem_and_record(
  p_operation,p_tenant,p_fingerprint,p_generation,p_issued_at,p_signature
 );
END;
$f139$;

-- F138 direct SQL caller must not bypass the locked admission path.
REVOKE EXECUTE ON FUNCTION perf_f138_primary.record_if_anchor_current(
  text,text,text,bigint,bigint,text) FROM perf_f138_primary_bridge;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f139_primary FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f139_primary TO perf_f139_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f139_primary.record_with_locked_anchor(
 text,text,text,bigint,bigint,text) TO perf_f139_primary_bridge;
GRANT USAGE ON SCHEMA perf_f132_primary TO perf_f139_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.current_generation()
 TO perf_f139_primary_bridge;
COMMIT;
