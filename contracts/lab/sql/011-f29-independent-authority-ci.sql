-- F1-29: separate PostgreSQL 16 instance for NON-OPERATIONAL token replay experiment.
-- Installed ONLY in perf_f29_authority_ci on 127.0.0.1:5433 during Actions.
-- The caller has EXECUTE privilege only; its ledger and HMAC secret stay private.
\set ON_ERROR_STOP on
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f29_authority;
REVOKE ALL ON SCHEMA perf_f29_authority FROM PUBLIC;
CREATE TABLE perf_f29_authority.issuer (
  id text PRIMARY KEY,
  secret bytea NOT NULL CHECK(octet_length(secret)=32)
);
INSERT INTO perf_f29_authority.issuer VALUES
  ('ci-independent',decode(:'f29_hex_key','hex'));
REVOKE ALL ON perf_f29_authority.issuer FROM PUBLIC;
CREATE TABLE perf_f29_authority.consumed_nonce (
  nonce_digest bytea PRIMARY KEY CHECK(octet_length(nonce_digest)=32),
  tenant_id uuid NOT NULL,
  expires_at bigint NOT NULL,
  created_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f29_authority.consumed_nonce FROM PUBLIC;

CREATE FUNCTION perf_f29_authority.consume_claim(claim text, expected_tenant uuid)
RETURNS boolean LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog
AS $$
DECLARE
  p text[];
  expiry bigint;
  now_seconds bigint;
  signing_key bytea;
  check_sig text;
  digest_nonce bytea;
  inserted bytea;
BEGIN
  IF expected_tenant IS NULL OR claim IS NULL OR length(claim)>300 THEN
    RETURN false;
  END IF;
  p:=string_to_array(claim,'|');
  IF array_length(p,1)<>5 THEN RETURN false; END IF;
  IF p[1] !~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'
     OR p[1]::uuid<>expected_tenant
     OR p[2] !~ '^[0-9]{10}$'
     OR p[3] !~ '^[a-z0-9-]{8,40}$'
     OR p[4]<>'perf-f26-ci'
     OR p[5] !~ '^[0-9a-f]{64}$' THEN
    RETURN false;
  END IF;
  expiry:=p[2]::bigint;
  now_seconds:=floor(extract(epoch from clock_timestamp()))::bigint;
  IF expiry<=now_seconds OR expiry>now_seconds+300 THEN RETURN false; END IF;
  SELECT secret INTO signing_key FROM perf_f29_authority.issuer WHERE id='ci-independent';
  IF signing_key IS NULL THEN RETURN false; END IF;
  check_sig:=encode(public.hmac(convert_to(array_to_string(p[1:4],'|'),'UTF8'),signing_key,'sha256'),'hex');
  IF check_sig<>p[5] THEN RETURN false; END IF;
  digest_nonce:=public.digest(convert_to('perf-f29-ci|'||p[3],'UTF8'),'sha256');
  INSERT INTO perf_f29_authority.consumed_nonce(nonce_digest,tenant_id,expires_at)
  VALUES(digest_nonce,expected_tenant,expiry)
  ON CONFLICT DO NOTHING RETURNING nonce_digest INTO inserted;
  RETURN inserted IS NOT NULL;
EXCEPTION WHEN invalid_text_representation OR numeric_value_out_of_range THEN
  RETURN false;
END;
$$;
REVOKE ALL ON FUNCTION perf_f29_authority.consume_claim(text,uuid) FROM PUBLIC;
COMMIT;
