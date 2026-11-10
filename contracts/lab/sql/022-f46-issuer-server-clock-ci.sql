-- FAST-PERF F1-46: independent issuing PostgreSQL uses DATABASE clock.
-- This wrapper delegates only to the F45 TEST-ONLY HMAC issuer after replacing
-- caller-supplied timestamp with clock_timestamp() on the DB server.
-- NO user-controlled time source is trusted. Still demo SQL, not KMS/S2S.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f46_issuer;
REVOKE ALL ON SCHEMA perf_f46_issuer FROM PUBLIC;
CREATE FUNCTION perf_f46_issuer.mint_server(
    p_action text,p_outbox bigint,p_owner text,p_generation bigint,
    p_nonce text,p_claimed_clock bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE real_clock bigint;
BEGIN
    real_clock:=floor(extract(epoch from clock_timestamp()))::bigint;
    RETURN perf_f45_issuer.mint(
        p_action,p_outbox,p_owner,p_generation,real_clock,p_nonce
    );
END;
$$;
REVOKE ALL ON FUNCTION perf_f46_issuer.mint_server(
  text,bigint,text,bigint,text,bigint) FROM PUBLIC;
COMMIT;
