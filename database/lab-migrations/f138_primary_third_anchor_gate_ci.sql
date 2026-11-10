-- F138 PRIMARY ONLY, CI disposable; SQL checks a third domain via postgres_fdw.
-- Not an atomic third-party endorsement: read-only FDW SELECT has an epoch TOCTOU gap.
BEGIN;
CREATE SCHEMA perf_f138_primary;
REVOKE ALL ON SCHEMA perf_f138_primary FROM PUBLIC;
CREATE SERVER perf_f138_anchor_ci FOREIGN DATA WRAPPER postgres_fdw
OPTIONS(host 'anchor',port '5432',dbname 'perf_f138_anchor_ci');
CREATE USER MAPPING FOR postgres SERVER perf_f138_anchor_ci
 OPTIONS(user 'perf_f138_anchor_reader',password :'anchor_reader_password');
CREATE FOREIGN TABLE perf_f138_primary.external_epoch (
 singleton integer,
 generation bigint,
 changed_at timestamptz
) SERVER perf_f138_anchor_ci
OPTIONS(schema_name 'perf_f138_anchor',table_name 'epoch_floor');

CREATE FUNCTION perf_f138_primary.record_if_anchor_current(
 p_operation text,p_tenant text,p_fingerprint text,p_generation bigint,
 p_issued_at bigint,p_signature text
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f138_primary
AS $f138$
DECLARE trusted_generation bigint;
BEGIN
 IF p_generation IS NULL OR p_generation<1 THEN RETURN false; END IF;
 -- Mandatory server-side external floor check. Unknown/unreachable => error;
 -- calling client MUST treat it as denied, not fall back.
 SELECT generation INTO trusted_generation
 FROM perf_f138_primary.external_epoch WHERE singleton=1;
 IF trusted_generation IS NULL OR trusted_generation<>p_generation THEN
   RETURN false;
 END IF;
 RETURN perf_f136_primary.redeem_and_record(
  p_operation,p_tenant,p_fingerprint,p_generation,p_issued_at,p_signature
 );
END;
$f138$;

-- Cut off direct F136 writer from new lab's operational identity.
REVOKE EXECUTE ON FUNCTION perf_f136_primary.redeem_and_record(
  text,text,text,bigint,bigint,text) FROM perf_f136_primary_bridge;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f138_primary FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f138_primary FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f138_primary TO perf_f138_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f138_primary.record_if_anchor_current(
  text,text,text,bigint,bigint,text) TO perf_f138_primary_bridge;
GRANT USAGE ON SCHEMA perf_f132_primary TO perf_f138_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.current_generation()
  TO perf_f138_primary_bridge;
COMMIT;
