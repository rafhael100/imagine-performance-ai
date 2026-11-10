-- FAST-PERF F1-50: a THIRD disposable PostgreSQL instance on 5434.
-- CI intentionally excludes this store from restores of the F41/F47 source
-- and Portal DB. It is NOT non-restorable, WORM, KMS or production authority.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f50_sentinel;
REVOKE ALL ON SCHEMA perf_f50_sentinel FROM PUBLIC;

CREATE TABLE perf_f50_sentinel.floor (
  singleton_id boolean PRIMARY KEY DEFAULT true CHECK(singleton_id),
  minimum_epoch bigint NOT NULL CHECK(minimum_epoch>0),
  changed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
-- F1-48 ended by restoring authority to epoch 10 from quarantined epoch 11.
INSERT INTO perf_f50_sentinel.floor(singleton_id,minimum_epoch)
VALUES(true,11);
REVOKE ALL ON perf_f50_sentinel.floor FROM PUBLIC;

CREATE TABLE perf_f50_sentinel.seen (
  nonce text PRIMARY KEY CHECK(nonce ~ '^f45-[a-z0-9-]{12,44}$'),
  epoch bigint NOT NULL CHECK(epoch>0),
  recorded_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f50_sentinel.seen FROM PUBLIC;

CREATE FUNCTION perf_f50_sentinel.check_and_reserve(
  p_epoch bigint,p_nonce text
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE floor_epoch bigint;
        inserted text;
BEGIN
  IF p_epoch IS NULL OR p_epoch<1
    OR p_nonce IS NULL OR p_nonce !~ '^f45-[a-z0-9-]{12,44}$'
  THEN RETURN 'invalid_sentinel_request_denied'; END IF;

  SELECT minimum_epoch INTO floor_epoch FROM perf_f50_sentinel.floor
   WHERE singleton_id=true FOR SHARE;
  IF NOT FOUND THEN RETURN 'sentinel_floor_missing_denied'; END IF;
  IF p_epoch<floor_epoch THEN RETURN 'restore_rollback_detected_denied'; END IF;
  IF p_epoch>floor_epoch THEN RETURN 'unreconciled_future_epoch_denied'; END IF;

  INSERT INTO perf_f50_sentinel.seen(nonce,epoch)
  VALUES(p_nonce,p_epoch)
  ON CONFLICT(nonce) DO NOTHING
  RETURNING nonce INTO inserted;
  IF inserted IS NULL THEN RETURN 'sentinel_nonce_replay_denied'; END IF;

  RETURN 'reserved_sentinel_mock:'||p_nonce||':'||p_epoch::text;
END;
$$;
REVOKE ALL ON FUNCTION perf_f50_sentinel.check_and_reserve(bigint,text)
FROM PUBLIC;

-- An administrator CI action may only move the minimum floor UP.
CREATE FUNCTION perf_f50_sentinel.advance_floor(p_new_epoch bigint)
RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE old_epoch bigint;
BEGIN
  IF p_new_epoch IS NULL OR p_new_epoch<1
  THEN RETURN 'invalid_floor_denied'; END IF;
  SELECT minimum_epoch INTO old_epoch
    FROM perf_f50_sentinel.floor WHERE singleton_id=true FOR UPDATE;
  IF NOT FOUND THEN RETURN 'sentinel_floor_missing_denied'; END IF;
  IF p_new_epoch<=old_epoch THEN RETURN 'floor_rollback_denied'; END IF;
  UPDATE perf_f50_sentinel.floor
    SET minimum_epoch=p_new_epoch,changed_at=clock_timestamp()
    WHERE singleton_id=true;
  RETURN 'floor_advanced_mock:'||p_new_epoch::text;
END;
$$;
REVOKE ALL ON FUNCTION perf_f50_sentinel.advance_floor(bigint) FROM PUBLIC;
COMMIT;
