-- F138: THIRD PostgreSQL16 disposable CI service; NOT offsite/durable production authority.
BEGIN;
CREATE SCHEMA perf_f138_anchor;
REVOKE ALL ON SCHEMA perf_f138_anchor FROM PUBLIC;
CREATE TABLE perf_f138_anchor.epoch_floor (
 singleton integer PRIMARY KEY CHECK (singleton=1),
 generation bigint NOT NULL CHECK (generation>=1),
 changed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
INSERT INTO perf_f138_anchor.epoch_floor(singleton,generation) VALUES(1,8);
CREATE FUNCTION perf_f138_anchor.advance_floor(p_new bigint)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f138_anchor
AS $f138$
DECLARE current_epoch bigint;
BEGIN
 SELECT generation INTO current_epoch FROM perf_f138_anchor.epoch_floor
  WHERE singleton=1 FOR UPDATE;
 IF current_epoch IS NULL OR p_new IS NULL OR p_new<=current_epoch THEN
   RETURN false;
 END IF;
 UPDATE perf_f138_anchor.epoch_floor SET generation=p_new,
  changed_at=clock_timestamp() WHERE singleton=1;
 RETURN true;
END;
$f138$;
REVOKE ALL ON ALL TABLES IN SCHEMA perf_f138_anchor FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f138_anchor FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f138_anchor TO perf_f138_anchor_reader;
GRANT SELECT ON perf_f138_anchor.epoch_floor TO perf_f138_anchor_reader;
COMMIT;
