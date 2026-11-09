-- FAST-PERF F1-37: DRY-RUN ONLY claim/ack of F35 disabled outbox.
-- This does NOT deliver, publish, bill or call provider APIs.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f37_dry_run;
REVOKE ALL ON SCHEMA perf_f37_dry_run FROM PUBLIC;

CREATE TABLE perf_f37_dry_run.leases (
    outbox_id bigint PRIMARY KEY
        REFERENCES perf_f35_intents.outbox(id) ON DELETE RESTRICT,
    owner_ref text NOT NULL,
    expires_at bigint NOT NULL,
    state text NOT NULL CHECK(state IN ('leased_mock','acknowledged_mock')),
    claimed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f37_dry_run.leases FROM PUBLIC;

CREATE FUNCTION perf_f37_dry_run.claim(p_owner text,p_now bigint)
RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE id_to_claim bigint;
        received bigint;
BEGIN
    IF p_owner IS NULL OR p_owner !~ '^worker-f37-[a-z0-9-]{1,20}$'
       OR p_now IS NULL OR p_now<1000000000 OR p_now>2100000000
    THEN RETURN 'invalid_claim_denied'; END IF;

    SELECT o.id INTO id_to_claim
      FROM perf_f35_intents.outbox o
      LEFT JOIN perf_f37_dry_run.leases l ON l.outbox_id=o.id
     WHERE o.dispatch_enabled=false
       AND (l.outbox_id IS NULL
            OR (l.state='leased_mock' AND l.expires_at<=p_now))
     ORDER BY o.id
     FOR UPDATE OF o SKIP LOCKED LIMIT 1;

    IF id_to_claim IS NULL THEN RETURN 'none'; END IF;

    INSERT INTO perf_f37_dry_run.leases(outbox_id,owner_ref,expires_at,state)
    VALUES(id_to_claim,p_owner,p_now+30,'leased_mock')
    ON CONFLICT (outbox_id) DO UPDATE
        SET owner_ref=EXCLUDED.owner_ref,
            expires_at=EXCLUDED.expires_at,
            state='leased_mock',
            claimed_at=clock_timestamp()
    WHERE perf_f37_dry_run.leases.state='leased_mock'
      AND perf_f37_dry_run.leases.expires_at<=p_now
    RETURNING outbox_id INTO received;

    IF received IS NULL THEN RETURN 'none'; END IF;
    RETURN 'claimed_mock:'||received::text;
END;
$$;
REVOKE ALL ON FUNCTION perf_f37_dry_run.claim(text,bigint) FROM PUBLIC;

CREATE FUNCTION perf_f37_dry_run.ack(
    p_outbox_id bigint,p_owner text,p_now bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
BEGIN
    IF p_outbox_id IS NULL OR p_outbox_id<=0
       OR p_owner IS NULL OR p_owner !~ '^worker-f37-[a-z0-9-]{1,20}$'
       OR p_now IS NULL OR p_now<1000000000 OR p_now>2100000000
    THEN RETURN 'invalid_ack_denied'; END IF;

    UPDATE perf_f37_dry_run.leases
       SET state='acknowledged_mock'
     WHERE outbox_id=p_outbox_id
       AND owner_ref=p_owner
       AND state='leased_mock'
       AND expires_at>p_now;
    IF FOUND THEN RETURN 'acknowledged_mock'; END IF;
    RETURN 'stale_lease_or_wrong_owner';
END;
$$;
REVOKE ALL ON FUNCTION perf_f37_dry_run.ack(bigint,text,bigint) FROM PUBLIC;
COMMIT;
