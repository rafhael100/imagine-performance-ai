-- FAST-PERF F1-46: local consuming PostgreSQL uses DATABASE clock.
-- The caller p_claimed_clock is ignored; this prevents clients extending TTL
-- or backdating HMAC permits just by supplying a forged timestamp.
-- Still vulnerable to skew between DB clocks, cached permits on external
-- revocation, replay after restoring both ledgers and compromised HMAC key.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f46_admission;
REVOKE ALL ON SCHEMA perf_f46_admission FROM PUBLIC;
CREATE FUNCTION perf_f46_admission.consume_server(
    p_token text,p_claimed_clock bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE real_clock bigint;
BEGIN
    real_clock:=floor(extract(epoch from clock_timestamp()))::bigint;
    RETURN perf_f45_admission.consume(p_token,real_clock);
END;
$$;
REVOKE ALL ON FUNCTION perf_f46_admission.consume_server(text,bigint)
 FROM PUBLIC;
COMMIT;
