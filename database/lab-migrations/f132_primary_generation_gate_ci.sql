-- F132: primary epoch gate is laboratory-only and migrates no production table.
-- Apply after F129 and F131, as disposable CI database owner.
BEGIN;
CREATE SCHEMA perf_f132_primary;
REVOKE ALL ON SCHEMA perf_f132_primary FROM PUBLIC;
CREATE TABLE perf_f132_primary.local_generation (
  singleton integer PRIMARY KEY CHECK (singleton = 1),
  generation bigint NOT NULL CHECK (generation >= 1),
  changed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
INSERT INTO perf_f132_primary.local_generation(singleton,generation) VALUES (1,8);

CREATE FUNCTION perf_f132_primary.current_generation()
RETURNS bigint LANGUAGE sql SECURITY DEFINER STABLE
SET search_path = pg_catalog, perf_f132_primary
AS $func$
  SELECT generation FROM perf_f132_primary.local_generation WHERE singleton=1;
$func$;

CREATE FUNCTION perf_f132_primary.record_with_generation(
  p_operation text, p_tenant text, p_fingerprint text, p_generation bigint
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, perf_f132_primary
AS $func$
DECLARE local_epoch bigint;
BEGIN
  IF p_generation IS NULL OR p_generation < 1 THEN RETURN false; END IF;
  SELECT generation INTO local_epoch
    FROM perf_f132_primary.local_generation WHERE singleton=1 FOR SHARE;
  IF local_epoch IS NULL OR local_epoch <> p_generation THEN RETURN false; END IF;
  -- Same transaction as the local generation check; F129 circuit remains mandatory.
  RETURN perf_f129_lab.record_intent(p_operation, p_tenant, p_fingerprint, p_generation);
END;
$func$;

-- Fence the previous F131 bridge: it must not call F129 directly.
REVOKE EXECUTE ON FUNCTION perf_f129_lab.record_intent(text,text,text,bigint)
  FROM perf_f131_primary_bridge;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f132_primary FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f132_primary FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f132_primary TO perf_f132_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.current_generation()
  TO perf_f132_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_primary.record_with_generation(text,text,text,bigint)
  TO perf_f132_primary_bridge;
COMMIT;
