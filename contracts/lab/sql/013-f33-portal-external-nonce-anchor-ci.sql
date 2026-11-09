-- F1-33 CI ONLY: independent receipt anchor in synthetic Portal PostgreSQL.
-- This is NOT an operational Portal migration and does not verify signatures.
\set ON_ERROR_STOP on
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f33_anchor;
REVOKE ALL ON SCHEMA perf_f33_anchor FROM PUBLIC;
CREATE TABLE perf_f33_anchor.used_nonces (
    nonce_hash bytea PRIMARY KEY CHECK (octet_length(nonce_hash)=32),
    tenant_ref text NOT NULL,
    service_ref text NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f33_anchor.used_nonces FROM PUBLIC;

CREATE FUNCTION perf_f33_anchor.consume_validated_nonce(
    p_nonce text, p_tenant text, p_service text,
    p_expires bigint, p_now bigint
) RETURNS boolean LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE seen_hash bytea;
        inserted bytea;
BEGIN
    IF p_nonce IS NULL OR p_nonce !~ '^[a-f0-9]{32}$'
        OR p_tenant IS DISTINCT FROM 'perf-demo-a'
        OR p_service IS DISTINCT FROM 'svc-demo-a'
        OR p_now IS NULL OR p_now<=0
        OR p_expires IS NULL OR p_expires<=p_now
        OR p_expires-p_now>30 THEN
        RETURN false;
    END IF;
    seen_hash := public.digest(
        convert_to('IMAGINE-PERF-F33-ANCHOR-V1|'||p_nonce,'UTF8'),'sha256'
    );
    INSERT INTO perf_f33_anchor.used_nonces(nonce_hash,tenant_ref,service_ref)
    VALUES(seen_hash,p_tenant,p_service)
    ON CONFLICT DO NOTHING RETURNING nonce_hash INTO inserted;
    RETURN inserted IS NOT NULL;
END;
$$;
REVOKE ALL ON FUNCTION perf_f33_anchor.consume_validated_nonce(
    text,text,text,bigint,bigint
) FROM PUBLIC;
COMMIT;
