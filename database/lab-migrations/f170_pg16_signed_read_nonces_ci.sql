-- FAST-PERF F1-170: synthetic-only PostgreSQL16 nonce consumption.
-- Read capability signature is verified in PHP F169 before this SQL call.
-- Separate CI gateway role, not the two tenant reader roles.
BEGIN;

CREATE ROLE perf_f170_gateway LOGIN PASSWORD 'ci-only-f170-gateway'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
GRANT USAGE ON SCHEMA perf_f162 TO perf_f170_gateway;

CREATE TABLE perf_f162.consumed_read_nonces (
    tenant text NOT NULL CHECK(tenant IN ('perf-demo-aurora','perf-demo-boreal')),
    request_id text NOT NULL CHECK(request_id ~ '^[a-z0-9_-]{16,64}$'),
    expires_at timestamptz NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    PRIMARY KEY(tenant,request_id)
);
ALTER TABLE perf_f162.consumed_read_nonces ENABLE ROW LEVEL SECURITY;
ALTER TABLE perf_f162.consumed_read_nonces FORCE ROW LEVEL SECURITY;
REVOKE ALL ON perf_f162.consumed_read_nonces FROM PUBLIC;
-- No table privileges to gateway or tenant logins.
-- SECURITY DEFINER function deliberately owns one INSERT-only operation.
CREATE FUNCTION perf_f162.f170_consume_signed_read_nonce(
    claimed_tenant text, claimed_request_id text, expiry_epoch bigint
)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f162 AS $$
DECLARE affected integer;
DECLARE current_epoch bigint;
BEGIN
    IF session_user<>'perf_f170_gateway'
       OR claimed_tenant NOT IN ('perf-demo-aurora','perf-demo-boreal')
       OR claimed_request_id !~ '^[a-z0-9_-]{16,64}$' THEN
        RETURN false;
    END IF;
    current_epoch:=extract(epoch from clock_timestamp())::bigint;
    IF expiry_epoch<=current_epoch OR expiry_epoch>current_epoch+60 THEN
        RETURN false;
    END IF;
    INSERT INTO perf_f162.consumed_read_nonces(tenant,request_id,expires_at)
    VALUES(claimed_tenant,claimed_request_id,to_timestamp(expiry_epoch))
    ON CONFLICT(tenant,request_id) DO NOTHING;
    GET DIAGNOSTICS affected=ROW_COUNT;
    RETURN affected=1;
END
$$;
REVOKE ALL ON FUNCTION perf_f162.f170_consume_signed_read_nonce(text,text,bigint)
    FROM PUBLIC;
GRANT EXECUTE ON FUNCTION perf_f162.f170_consume_signed_read_nonce(text,text,bigint)
    TO perf_f170_gateway;
COMMIT;
