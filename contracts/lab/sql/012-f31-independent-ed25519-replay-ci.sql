-- F1-31: Ed25519 replay receipt in a SECOND, disposable PostgreSQL 16.
-- DB perf_f31_nonce_ci is not Portal's SQL authority and not Performance DB.
-- This SQL does NOT authenticate Portal or verify Ed25519; the PHP gate does.
\set ON_ERROR_STOP on
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f31_receipts;
REVOKE ALL ON SCHEMA perf_f31_receipts FROM PUBLIC;
CREATE TABLE perf_f31_receipts.used_nonces (
    fingerprint bytea PRIMARY KEY CHECK (octet_length(fingerprint)=32),
    tenant_ref text NOT NULL,
    service_ref text NOT NULL,
    expires_at bigint NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f31_receipts.used_nonces FROM PUBLIC;

CREATE FUNCTION perf_f31_receipts.consume_validated_nonce(
    p_nonce text, p_tenant text, p_service text, p_expires bigint, p_now bigint
) RETURNS boolean LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog
AS $$
DECLARE digest_value bytea;
        inserted bytea;
BEGIN
    -- Fixture restrictions. Auth and revocation remain the PHP/Portal gates.
    IF p_nonce IS NULL OR p_nonce !~ '^[0-9a-f]{32}$'
        OR p_tenant IS DISTINCT FROM 'perf-demo-a'
        OR p_service IS DISTINCT FROM 'svc-demo-a'
        OR p_now IS NULL OR p_now<=0
        OR p_expires IS NULL OR p_expires<=p_now
        OR p_expires-p_now>30 THEN
        RETURN false;
    END IF;
    digest_value:=public.digest(
        convert_to('IMAGINE-PERF-F31-ED25519-NONCE-V1|'||p_nonce,'UTF8'),
        'sha256'
    );
    INSERT INTO perf_f31_receipts.used_nonces
        (fingerprint,tenant_ref,service_ref,expires_at)
    VALUES(digest_value,p_tenant,p_service,p_expires)
    ON CONFLICT DO NOTHING RETURNING fingerprint INTO inserted;
    RETURN inserted IS NOT NULL;
END;
$$;
REVOKE ALL ON FUNCTION perf_f31_receipts.consume_validated_nonce(
    text,text,text,bigint,bigint
) FROM PUBLIC;
COMMIT;
