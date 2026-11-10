-- F130: external witness in a SECOND, disposable PostgreSQL16 container, not production.
BEGIN;
CREATE SCHEMA perf_f130_witness;
REVOKE ALL ON SCHEMA perf_f130_witness FROM PUBLIC;
CREATE TABLE perf_f130_witness.seen_fingerprints (
    fingerprint text PRIMARY KEY CHECK (fingerprint ~ '^[a-f0-9]{64}$'),
    noted_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
CREATE FUNCTION perf_f130_witness.record_fingerprint(p_fingerprint text)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path = pg_catalog, perf_f130_witness
AS $f130$
DECLARE inserted_count integer;
BEGIN
  IF p_fingerprint IS NULL OR p_fingerprint !~ '^[a-f0-9]{64}$' THEN
    RETURN false;
  END IF;
  INSERT INTO perf_f130_witness.seen_fingerprints (fingerprint)
    VALUES (p_fingerprint) ON CONFLICT DO NOTHING;
  GET DIAGNOSTICS inserted_count = ROW_COUNT;
  RETURN inserted_count = 1;
END;
$f130$;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f130_witness FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f130_witness FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f130_witness TO perf_f130_writer;
GRANT EXECUTE ON FUNCTION perf_f130_witness.record_fingerprint(text) TO perf_f130_writer;
COMMIT;
