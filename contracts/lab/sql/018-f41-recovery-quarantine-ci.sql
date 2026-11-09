-- F1-41: quarantined by default, in independent disposable PostgreSQL.
-- Installs AFTER F40 negative dual-restore CI proof. No real deploy.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f41_recovery;
REVOKE ALL ON SCHEMA perf_f41_recovery FROM PUBLIC;

CREATE TABLE perf_f41_recovery.guard_state (
  singleton_id boolean PRIMARY KEY DEFAULT true CHECK(singleton_id),
  status text NOT NULL CHECK(status IN ('quarantined','released_mock')),
  epoch bigint NOT NULL CHECK(epoch>=1),
  reason text NOT NULL CHECK(length(reason)>=6),
  approval_ref text NULL,
  changed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
  CONSTRAINT mock_release_requires_ref CHECK (
    status<>'released_mock' OR (
      approval_ref IS NOT NULL
      AND approval_ref ~ '^ci-approved-[a-z0-9-]{6,60}$'
    )
  )
);
INSERT INTO perf_f41_recovery.guard_state(
  singleton_id,status,epoch,reason,approval_ref
) VALUES (true,'quarantined',1,'default_denied_after_restore',NULL);
REVOKE ALL ON perf_f41_recovery.guard_state FROM PUBLIC;

-- Consumer can only read this synthetic state, never release or reset.
CREATE FUNCTION perf_f41_recovery.read_state()
RETURNS text LANGUAGE sql STABLE SECURITY DEFINER
SET search_path=pg_catalog AS $$
  SELECT status||':'||epoch::text
    FROM perf_f41_recovery.guard_state
   WHERE singleton_id=true;
$$;
REVOKE ALL ON FUNCTION perf_f41_recovery.read_state() FROM PUBLIC;
COMMIT;
