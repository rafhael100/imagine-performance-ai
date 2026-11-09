-- F1-43 disposable CI only: enforce quarantine IN THE SAME Portal SQL transaction
-- before legacy F38 dry-run claim/ack. Does NOT authenticate external F41 state,
-- and a local database restore can roll back this guard. No provider dispatch.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f43_sql_guard;
REVOKE ALL ON SCHEMA perf_f43_sql_guard FROM PUBLIC;

CREATE TABLE perf_f43_sql_guard.state (
  singleton_id boolean PRIMARY KEY DEFAULT true CHECK(singleton_id),
  status text NOT NULL CHECK(status IN ('quarantined','released_mock')),
  epoch bigint NOT NULL CHECK(epoch>=1),
  approval_ref text,
  reason text NOT NULL CHECK(length(reason)>=8),
  CHECK (
     status<>'released_mock'
     OR (approval_ref IS NOT NULL AND
         approval_ref ~ '^ci-approved-[a-z0-9-]{6,60}$')
  )
);
INSERT INTO perf_f43_sql_guard.state(
  singleton_id,status,epoch,approval_ref,reason
) VALUES(true,'quarantined',4,NULL,'default deny after F42 cutover');
REVOKE ALL ON perf_f43_sql_guard.state FROM PUBLIC;

CREATE FUNCTION perf_f43_sql_guard.read_state()
RETURNS text LANGUAGE sql STABLE SECURITY DEFINER
SET search_path=pg_catalog AS $$
  SELECT status||':'||epoch::text
  FROM perf_f43_sql_guard.state
  WHERE singleton_id=true;
$$;
REVOKE ALL ON FUNCTION perf_f43_sql_guard.read_state() FROM PUBLIC;

-- Local state row is SHARE-locked until this SQL statement commits.
-- Operator SQL transition takes conflicting row lock and serializes, unlike
-- PHP before/after TOCTOU checks. Only this LOCAL domain is protected.
CREATE FUNCTION perf_f43_sql_guard.claim(
    p_owner text,p_now bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE current_state text;
        current_epoch bigint;
        result text;
BEGIN
    SELECT status,epoch INTO current_state,current_epoch
    FROM perf_f43_sql_guard.state
    WHERE singleton_id=true
    FOR SHARE;
    IF NOT FOUND OR current_state IS DISTINCT FROM 'released_mock'
    THEN RETURN 'sql_quarantine_denied'; END IF;

    result:=perf_f38_fenced.claim(p_owner,p_now);
    IF result ~ '^claimed_mock:[1-9][0-9]*:[1-9][0-9]*$' THEN
        RETURN 'claimed_sql_mock:'||split_part(result,':',2)
               ||':'||split_part(result,':',3)||':'||current_epoch::text;
    END IF;
    RETURN result;
END;
$$;
REVOKE ALL ON FUNCTION perf_f43_sql_guard.claim(text,bigint) FROM PUBLIC;

CREATE FUNCTION perf_f43_sql_guard.ack(
    p_outbox_id bigint,p_owner text,p_generation bigint,
    p_epoch bigint,p_now bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE current_state text;
        current_epoch bigint;
        result text;
BEGIN
    SELECT status,epoch INTO current_state,current_epoch
    FROM perf_f43_sql_guard.state
    WHERE singleton_id=true
    FOR SHARE;
    IF NOT FOUND OR current_state IS DISTINCT FROM 'released_mock'
    THEN RETURN 'sql_quarantine_denied'; END IF;

    IF p_epoch IS DISTINCT FROM current_epoch
    THEN RETURN 'stale_sql_recovery_epoch_denied'; END IF;

    result:=perf_f38_fenced.ack(p_outbox_id,p_owner,p_generation,p_now);
    IF result='acknowledged_mock' THEN RETURN 'acknowledged_sql_mock'; END IF;
    RETURN result;
END;
$$;
REVOKE ALL ON FUNCTION perf_f43_sql_guard.ack(bigint,text,bigint,bigint,bigint)
 FROM PUBLIC;
COMMIT;
