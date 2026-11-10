-- F134 disposable CI ONLY: SQL itself verifies witness HMAC before accepting
-- an intent. Allows no unauthenticated direct registration by the CI bridge.
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f134_primary;
REVOKE ALL ON SCHEMA perf_f134_primary FROM PUBLIC;

CREATE TABLE perf_f134_primary.key_material (
  singleton integer PRIMARY KEY CHECK (singleton=1),
  secret bytea NOT NULL CHECK (octet_length(secret)=32)
);
INSERT INTO perf_f134_primary.key_material(singleton,secret)
VALUES (1,decode(:'ci_key','hex'));

CREATE FUNCTION perf_f134_primary.record_signed(
 p_operation text,p_tenant text,p_fingerprint text,p_generation bigint,
 p_issued_at bigint,p_signature text
) RETURNS boolean
LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f134_primary
AS $f134$
DECLARE
 v_key bytea;
 v_payload text;
 v_sig text;
 v_now bigint;
BEGIN
 IF p_operation IS NULL OR p_operation !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_tenant IS NULL OR p_tenant !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$'
 OR p_generation IS NULL OR p_generation < 1
 OR p_issued_at IS NULL OR p_signature IS NULL
 OR p_signature !~ '^[a-f0-9]{64}$' THEN
   RETURN false;
 END IF;
 v_now := floor(extract(epoch from clock_timestamp()))::bigint;
 IF p_issued_at < v_now - 30 OR p_issued_at > v_now + 5 THEN
   RETURN false;
 END IF;
 SELECT secret INTO STRICT v_key FROM perf_f134_primary.key_material WHERE singleton=1;
 v_payload := format('F134-v1|%s|%s|%s|%s|%s',
   p_operation,p_tenant,p_fingerprint,p_generation,p_issued_at);
 v_sig := encode(public.hmac(convert_to(v_payload,'UTF8'),v_key,'sha256'),'hex');
 IF v_sig <> p_signature THEN
   RETURN false;
 END IF;
 -- Guarded local epoch and sticky circuit from previous stages remain mandatory.
 RETURN perf_f132_primary.record_with_generation(
   p_operation,p_tenant,p_fingerprint,p_generation
 );
END;
$f134$;

REVOKE ALL ON ALL TABLES IN SCHEMA perf_f134_primary FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f134_primary FROM PUBLIC;
-- Prior credential cannot invoke SQL-side registration bypass.
REVOKE EXECUTE ON FUNCTION perf_f132_primary.record_with_generation(text,text,text,bigint)
  FROM perf_f132_primary_bridge;
GRANT USAGE ON SCHEMA perf_f134_primary TO perf_f134_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f134_primary.record_signed(text,text,text,bigint,bigint,text)
  TO perf_f134_primary_bridge;
COMMIT;
