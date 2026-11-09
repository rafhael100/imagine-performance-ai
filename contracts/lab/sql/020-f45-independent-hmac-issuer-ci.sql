-- FAST-PERF F1-45: independent PostgreSQL 5433 synthetic signed permits.
-- A fresh random 32-byte TEST-ONLY HMAC key is supplied by GitHub Actions
-- via psql variable fixture_key; do not reuse this mechanism in production.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f45_issuer;
REVOKE ALL ON SCHEMA perf_f45_issuer FROM PUBLIC;
CREATE TABLE perf_f45_issuer.signing_key(
  singleton_id boolean PRIMARY KEY DEFAULT true CHECK(singleton_id),
  secret bytea NOT NULL CHECK(octet_length(secret)=32)
);
INSERT INTO perf_f45_issuer.signing_key
VALUES(true,decode(:'fixture_key','hex'));
REVOKE ALL ON perf_f45_issuer.signing_key FROM PUBLIC;

CREATE TABLE perf_f45_issuer.issued(
  nonce text PRIMARY KEY,
  action text NOT NULL CHECK(action IN('claim','ack')),
  recovery_epoch bigint NOT NULL,
  issued_at bigint NOT NULL
);
REVOKE ALL ON perf_f45_issuer.issued FROM PUBLIC;

CREATE FUNCTION perf_f45_issuer.mint(
  p_action text,p_outbox bigint,p_owner text,p_generation bigint,
  p_now bigint,p_nonce text
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE st text;
        ep bigint;
        key_bytes bytea;
        payload text;
        signature text;
BEGIN
  IF p_action NOT IN ('claim','ack')
     OR p_owner IS NULL OR p_owner !~ '^worker-f38-[a-z0-9-]{1,20}$'
     OR p_now IS NULL OR p_now<1000000000 OR p_now>2100000000
     OR p_nonce IS NULL OR p_nonce !~ '^f45-[a-z0-9-]{12,44}$'
     OR p_outbox IS NULL OR p_generation IS NULL
     OR (p_action='claim' AND (p_outbox<>0 OR p_generation<>0))
     OR (p_action='ack' AND (p_outbox<=0 OR p_generation<=0))
  THEN RETURN 'invalid_permit_request_denied'; END IF;

  SELECT status,epoch INTO st,ep
  FROM perf_f41_recovery.guard_state WHERE singleton_id=true FOR SHARE;
  IF NOT FOUND OR st IS DISTINCT FROM 'released_mock'
  THEN RETURN 'external_quarantine_denied'; END IF;

  INSERT INTO perf_f45_issuer.issued(nonce,action,recovery_epoch,issued_at)
  VALUES(p_nonce,p_action,ep,p_now) ON CONFLICT(nonce) DO NOTHING;
  IF NOT FOUND THEN RETURN 'nonce_already_issued_denied'; END IF;

  SELECT secret INTO key_bytes
  FROM perf_f45_issuer.signing_key WHERE singleton_id=true;
  IF key_bytes IS NULL THEN RAISE EXCEPTION 'TEST KEY NOT PRESENT'; END IF;

  -- Canonical delimiter-safe tuple, no escaping required by input regexes.
  payload:='f45|'||p_action||'|'||p_outbox::text||'|'||p_owner
   ||'|'||p_generation::text||'|'||ep::text
   ||'|'||p_now::text||'|'||(p_now+30)::text||'|'||p_nonce;
  signature:=encode(public.hmac(convert_to(payload,'UTF8'),key_bytes,'sha256'),'hex');
  RETURN payload||'|'||signature;
END;
$$;
REVOKE ALL ON FUNCTION perf_f45_issuer.mint(
  text,bigint,text,bigint,bigint,text) FROM PUBLIC;
COMMIT;
