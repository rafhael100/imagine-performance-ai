-- FAST-PERF F1-28: nonce fence solely in a disposable Postgres CI database.
-- Install AFTER the F1-26 experimental signed-tenant policies.
-- Never install in production or as an application migration.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f28_replay;
REVOKE ALL ON SCHEMA perf_f28_replay FROM PUBLIC;
CREATE TABLE perf_f28_replay.used_nonces (
    nonce_digest bytea PRIMARY KEY CHECK(octet_length(nonce_digest)=32),
    tenant_id uuid NOT NULL,
    expires_at bigint NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f28_replay.used_nonces FROM PUBLIC;

-- An explicit consume is necessary: the F1-26 RLS verifier is STABLE/read-only.
-- PostgreSQL's unique constraint serializes competing consumers at commit.
-- A rolled-back transaction rolls back nonce consumption as well: this is
-- NOT durable anti-replay after abort, and must not gate external effects.
CREATE FUNCTION perf_f28_replay.consume_valid_nonce(expected_tenant uuid)
RETURNS boolean LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog
AS $$
DECLARE
    actual_tenant uuid;
    claim text;
    parts text[];
    claimed_expiry bigint;
    inserted bytea;
    fingerprint bytea;
BEGIN
    IF expected_tenant IS NULL THEN
        RETURN false;
    END IF;
    actual_tenant := perf_f26_identity.verified_tenant();
    IF actual_tenant IS NULL OR actual_tenant <> expected_tenant THEN
        RETURN false;
    END IF;
    claim := current_setting('app.performance_tenant_claim',true);
    parts := string_to_array(claim, '|');
    IF array_length(parts,1) <> 5 THEN
        RETURN false;
    END IF;
    claimed_expiry := parts[2]::bigint;
    fingerprint := public.digest(convert_to('perf-f28-ci|' || parts[3], 'UTF8'),'sha256');
    INSERT INTO perf_f28_replay.used_nonces(nonce_digest,tenant_id,expires_at)
    VALUES(fingerprint,actual_tenant,claimed_expiry)
    ON CONFLICT DO NOTHING RETURNING nonce_digest INTO inserted;
    RETURN inserted IS NOT NULL;
EXCEPTION WHEN invalid_text_representation OR numeric_value_out_of_range THEN
    RETURN false;
END;
$$;
REVOKE ALL ON FUNCTION perf_f28_replay.consume_valid_nonce(uuid) FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f28_replay TO perf_campaign_test_user;
GRANT EXECUTE ON FUNCTION perf_f28_replay.consume_valid_nonce(uuid)
    TO perf_campaign_test_user;
COMMIT;
