-- F131 CI-only: remove the old direct F129 path; only bridge role may register.
-- Apply after F129 schema, with both roles created by the disposable CI job.
BEGIN;
REVOKE EXECUTE ON FUNCTION perf_f129_lab.record_intent(text,text,text,bigint) FROM perf_f129_writer;
GRANT USAGE ON SCHEMA perf_f129_lab TO perf_f131_primary_bridge;
GRANT EXECUTE ON FUNCTION perf_f129_lab.record_intent(text,text,text,bigint) TO perf_f131_primary_bridge;
-- Explicitly do not grant SELECT, DML, incident mutations, snapshot or any API route.
COMMIT;
