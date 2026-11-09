-- FAST-PERF F1-39: independent HIGH-WATERMARK on the SECOND disposable PostgreSQL.
-- Database perf_f31_nonce_ci, 127.0.0.1:5433, NOT Portal on 5432.
-- No provider actions; this SQL does not authenticate the worker or Ed25519.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f39_fence;
REVOKE ALL ON SCHEMA perf_f39_fence FROM PUBLIC;
CREATE TABLE perf_f39_fence.generations (
    outbox_id bigint PRIMARY KEY CHECK(outbox_id>0),
    highest_generation bigint NOT NULL CHECK(highest_generation>=1),
    updated_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f39_fence.generations FROM PUBLIC;

CREATE FUNCTION perf_f39_fence.advance(
    p_outbox_id bigint,p_generation bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE saved bigint;
BEGIN
    IF p_outbox_id IS NULL OR p_outbox_id<=0
       OR p_generation IS NULL OR p_generation<=0
    THEN RETURN 'invalid_generation_denied'; END IF;

    INSERT INTO perf_f39_fence.generations(outbox_id,highest_generation)
    VALUES (p_outbox_id,p_generation)
    ON CONFLICT(outbox_id) DO UPDATE
       SET highest_generation=EXCLUDED.highest_generation,
           updated_at=clock_timestamp()
     WHERE perf_f39_fence.generations.highest_generation
           < EXCLUDED.highest_generation
    RETURNING highest_generation INTO saved;

    IF saved IS NULL THEN RETURN 'rollback_or_replay_generation_denied'; END IF;
    RETURN 'observed_synthetic_generation:'||saved::text;
END;
$$;
REVOKE ALL ON FUNCTION perf_f39_fence.advance(bigint,bigint) FROM PUBLIC;
COMMIT;
