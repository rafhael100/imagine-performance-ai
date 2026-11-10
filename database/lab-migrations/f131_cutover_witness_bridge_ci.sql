-- F131 CI-only: remove earlier F130 direct writer access.
BEGIN;
REVOKE EXECUTE ON FUNCTION perf_f130_witness.record_fingerprint(text) FROM perf_f130_writer;
GRANT USAGE ON SCHEMA perf_f130_witness TO perf_f131_witness_bridge;
GRANT EXECUTE ON FUNCTION perf_f130_witness.record_fingerprint(text) TO perf_f131_witness_bridge;
COMMIT;
