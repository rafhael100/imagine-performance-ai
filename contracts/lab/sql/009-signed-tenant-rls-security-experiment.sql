-- FAST-PERF F1-26: DISPOSABLE CI SECURITY EXPERIMENT ONLY.
-- Run via psql -v f26_hex_key=<ephemeral 64-hex> on perf_test_campaign_registry.
-- This deliberately REPLACES RLS policies in the CI database after existing
-- Laravel F1-22/23/24/25 regressions. It does NOT migrate the app database.
--
-- A per-tenant claim is HMAC-signed by a fictional trusted issuer. This is
-- NOT real Portal identity, not production trust, not key management or MFA.
\set ON_ERROR_STOP on
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f26_identity;
REVOKE ALL ON SCHEMA perf_f26_identity FROM PUBLIC;
CREATE TABLE perf_f26_identity.test_issuer (
    id text PRIMARY KEY,
    secret bytea NOT NULL CHECK (octet_length(secret)=32)
);
REVOKE ALL ON perf_f26_identity.test_issuer FROM PUBLIC;
INSERT INTO perf_f26_identity.test_issuer(id,secret)
VALUES ('ci-issuer',decode(:'f26_hex_key','hex'));

CREATE FUNCTION perf_f26_identity.verified_tenant()
RETURNS uuid LANGUAGE plpgsql STABLE SECURITY DEFINER
SET search_path=pg_catalog
AS $$
DECLARE
    claim text;
    parts text[];
    payload text;
    expected text;
    key_bytes bytea;
    now_seconds bigint;
    expiry bigint;
    candidate uuid;
BEGIN
    claim := current_setting('app.performance_tenant_claim',true);
    IF claim IS NULL OR length(claim)>300 OR claim='' THEN
        RETURN NULL;
    END IF;
    parts := string_to_array(claim,'|');
    IF array_length(parts,1)<>5 THEN
        RETURN NULL;
    END IF;
    IF parts[1] !~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
       OR parts[2] !~ '^[0-9]{10}$'
       OR parts[3] !~ '^[a-z0-9-]{8,40}$'
       OR parts[4] <> 'perf-f26-ci'
       OR parts[5] !~ '^[0-9a-f]{64}$' THEN
        RETURN NULL;
    END IF;
    expiry:=parts[2]::bigint;
    now_seconds:=floor(extract(epoch from clock_timestamp()))::bigint;
    IF expiry<=now_seconds OR expiry>now_seconds+300 THEN
        RETURN NULL;
    END IF;
    SELECT secret INTO key_bytes
      FROM perf_f26_identity.test_issuer WHERE id='ci-issuer';
    IF key_bytes IS NULL THEN
        RETURN NULL;
    END IF;
    payload:=array_to_string(parts[1:4],'|');
    expected:=encode(public.hmac(convert_to(payload,'UTF8'),key_bytes,'sha256'),'hex');
    IF expected<>parts[5] THEN
        RETURN NULL;
    END IF;
    candidate:=parts[1]::uuid;
    RETURN candidate;
EXCEPTION WHEN invalid_text_representation OR numeric_value_out_of_range THEN
    RETURN NULL;
END;
$$;

REVOKE ALL ON FUNCTION perf_f26_identity.verified_tenant() FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f26_identity TO perf_campaign_test_user;
GRANT EXECUTE ON FUNCTION perf_f26_identity.verified_tenant()
TO perf_campaign_test_user;

DROP POLICY perf_campaign_tenant_boundary ON performance_managed_campaigns;
CREATE POLICY perf_campaign_tenant_boundary
ON performance_managed_campaigns
FOR ALL
USING (tenant_id=perf_f26_identity.verified_tenant())
WITH CHECK (tenant_id=perf_f26_identity.verified_tenant());

DROP POLICY perf_campaign_tenant_boundary ON performance_campaign_intents;
CREATE POLICY perf_campaign_tenant_boundary
ON performance_campaign_intents
FOR ALL
USING (tenant_id=perf_f26_identity.verified_tenant())
WITH CHECK (tenant_id=perf_f26_identity.verified_tenant());
COMMIT;
