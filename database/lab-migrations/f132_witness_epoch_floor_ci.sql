-- F132: external minimum epoch in a SECOND disposable PostgreSQL16 container.
-- This is not offsite or rollback-proof; owner and local admin can alter the floor.
BEGIN;
CREATE SCHEMA perf_f132_witness;
REVOKE ALL ON SCHEMA perf_f132_witness FROM PUBLIC;
CREATE TABLE perf_f132_witness.epoch_floor (
  singleton integer PRIMARY KEY CHECK (singleton = 1),
  generation bigint NOT NULL CHECK (generation >= 1),
  changed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
INSERT INTO perf_f132_witness.epoch_floor(singleton,generation) VALUES (1,8);

CREATE FUNCTION perf_f132_witness.reserve_if_current(
  p_fingerprint text, p_generation bigint
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, perf_f132_witness
AS $func$
DECLARE floor_epoch bigint;
BEGIN
  IF p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$'
     OR p_generation IS NULL OR p_generation < 1 THEN RETURN false; END IF;
  SELECT generation INTO floor_epoch
    FROM perf_f132_witness.epoch_floor WHERE singleton = 1 FOR SHARE;
  IF floor_epoch IS NULL OR floor_epoch <> p_generation THEN RETURN false; END IF;
  -- Witness nonce is reserved IN THE SAME TRANSACTION as epoch comparison.
  RETURN perf_f130_witness.record_fingerprint(p_fingerprint);
END;
$func$;

-- Only database administrator may advance the monotonic floor during CI.
-- No method to lower epoch exposed through login roles.
CREATE FUNCTION perf_f132_witness.advance_floor(p_new bigint)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, perf_f132_witness
AS $func$
DECLARE old_epoch bigint;
BEGIN
  SELECT generation INTO old_epoch FROM perf_f132_witness.epoch_floor
    WHERE singleton = 1 FOR UPDATE;
  IF p_new IS NULL OR old_epoch IS NULL OR p_new <= old_epoch THEN RETURN false; END IF;
  UPDATE perf_f132_witness.epoch_floor
    SET generation=p_new, changed_at=clock_timestamp() WHERE singleton=1;
  RETURN true;
END;
$func$;

REVOKE EXECUTE ON FUNCTION perf_f130_witness.record_fingerprint(text)
  FROM perf_f131_witness_bridge;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f132_witness FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f132_witness FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f132_witness TO perf_f132_witness_bridge;
GRANT EXECUTE ON FUNCTION perf_f132_witness.reserve_if_current(text,bigint)
  TO perf_f132_witness_bridge;
COMMIT;
