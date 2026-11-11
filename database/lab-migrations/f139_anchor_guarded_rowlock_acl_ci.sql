-- F139 CI-only third-anchor ACL: PostgreSQL row locks on remote tables
-- require UPDATE privilege in addition to SELECT. Give a single PK column,
-- but a trigger prevents all actual changes by the bridge login.
BEGIN;
CREATE SCHEMA perf_f139_anchor;
REVOKE ALL ON SCHEMA perf_f139_anchor FROM PUBLIC;
CREATE FUNCTION perf_f139_anchor.forbid_bridge_write()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f139_anchor
AS $f139$
BEGIN
  IF session_user = 'perf_f138_anchor_reader' THEN
    RAISE EXCEPTION 'F139 anchor reader is prohibited from UPDATE'
      USING ERRCODE='42501';
  END IF;
  RETURN NEW;
END;
$f139$;
CREATE TRIGGER f139_reader_update_guard
 BEFORE UPDATE ON perf_f138_anchor.epoch_floor
 FOR EACH ROW EXECUTE FUNCTION perf_f139_anchor.forbid_bridge_write();

REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f139_anchor FROM PUBLIC;
-- Column-level permission sufficient for row-lock feature; no UPDATE on
-- generation. A direct no-op UPDATE on singleton hits the trigger and fails.
GRANT UPDATE(singleton) ON perf_f138_anchor.epoch_floor
 TO perf_f138_anchor_reader;
COMMIT;
