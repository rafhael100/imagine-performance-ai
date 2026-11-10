-- FAST-PERF F1-38: simulate a generation-fenced lease; no dispatch.
-- Requires F35 outbox and F37 lab; intended for disposable Postgres only.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f38_fenced;
REVOKE ALL ON SCHEMA perf_f38_fenced FROM PUBLIC;
CREATE TABLE perf_f38_fenced.leases (
    outbox_id bigint PRIMARY KEY
        REFERENCES perf_f35_intents.outbox(id) ON DELETE RESTRICT,
    owner_ref text NOT NULL,
    generation bigint NOT NULL CHECK (generation>=1),
    expires_at bigint NOT NULL,
    state text NOT NULL CHECK (state IN ('leased_mock','acknowledged_mock'))
);
REVOKE ALL ON perf_f38_fenced.leases FROM PUBLIC;

-- Atomic claim with row locking; monotonic generation only WITHIN THIS DB.
-- Excludes rows ever claimed by the F37 legacy fixture to avoid conflicting
-- ledgers during this CI comparison. Caller clock/owner are untrusted in reality.
CREATE FUNCTION perf_f38_fenced.claim(p_owner text,p_now bigint)
RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE selected_id bigint;
        issued_generation bigint;
BEGIN
    IF p_owner IS NULL OR p_owner !~ '^worker-f38-[a-z0-9-]{1,20}$'
       OR p_now IS NULL OR p_now<1000000000 OR p_now>2100000000
    THEN RETURN 'invalid_claim_denied'; END IF;

    SELECT o.id INTO selected_id
      FROM perf_f35_intents.outbox o
      LEFT JOIN perf_f38_fenced.leases l ON l.outbox_id=o.id
     WHERE o.dispatch_enabled=false
       AND NOT EXISTS (
           SELECT 1 FROM perf_f37_dry_run.leases old
           WHERE old.outbox_id=o.id
       )
       AND (
          l.outbox_id IS NULL OR
          (l.state='leased_mock' AND l.expires_at<=p_now)
       )
     ORDER BY o.id
     FOR UPDATE OF o SKIP LOCKED LIMIT 1;

    IF selected_id IS NULL THEN RETURN 'none'; END IF;

    INSERT INTO perf_f38_fenced.leases(
        outbox_id,owner_ref,generation,expires_at,state
    ) VALUES(selected_id,p_owner,1,p_now+30,'leased_mock')
    ON CONFLICT (outbox_id) DO UPDATE
       SET owner_ref=EXCLUDED.owner_ref,
           generation=perf_f38_fenced.leases.generation+1,
           expires_at=EXCLUDED.expires_at,
           state='leased_mock'
     WHERE perf_f38_fenced.leases.state='leased_mock'
       AND perf_f38_fenced.leases.expires_at<=p_now
    RETURNING generation INTO issued_generation;

    IF issued_generation IS NULL THEN RETURN 'none'; END IF;
    RETURN 'claimed_mock:'||selected_id::text||':'||issued_generation::text;
END;
$$;
REVOKE ALL ON FUNCTION perf_f38_fenced.claim(text,bigint) FROM PUBLIC;

CREATE FUNCTION perf_f38_fenced.ack(
    p_outbox_id bigint,p_owner text,p_generation bigint,p_now bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
BEGIN
    IF p_outbox_id IS NULL OR p_outbox_id<=0
       OR p_owner IS NULL OR p_owner !~ '^worker-f38-[a-z0-9-]{1,20}$'
       OR p_generation IS NULL OR p_generation<1
       OR p_now IS NULL OR p_now<1000000000 OR p_now>2100000000
    THEN RETURN 'invalid_ack_denied'; END IF;
    UPDATE perf_f38_fenced.leases SET state='acknowledged_mock'
     WHERE outbox_id=p_outbox_id AND owner_ref=p_owner
       AND generation=p_generation
       AND state='leased_mock' AND expires_at>p_now;
    IF FOUND THEN RETURN 'acknowledged_mock'; END IF;
    RETURN 'fenced_lease_denied';
END;
$$;
REVOKE ALL ON FUNCTION perf_f38_fenced.ack(bigint,text,bigint,bigint) FROM PUBLIC;
COMMIT;
