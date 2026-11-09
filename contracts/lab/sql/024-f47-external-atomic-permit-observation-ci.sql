-- FAST-PERF F1-47: the AUTHORITY decides whether a signed mock
-- permit may be observed. The F41 quarantine row and receipt insertion
-- belong to the SAME PostgreSQL 5433 transaction. SQL "FOR SHARE" keeps
-- an operator quarantine UPDATE blocked until observation commits.
--
-- Deliberately NO operational claim/ACK, no local dispatch and no cross-DB
-- transaction. This fixes ONLY the F46 stale-bearer *authority admission*
-- bypass; old local paths must be revoked separately. CI-only experiment.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f47_authority;
REVOKE ALL ON SCHEMA perf_f47_authority FROM PUBLIC;

CREATE TABLE perf_f47_authority.observed (
  nonce text PRIMARY KEY CHECK(nonce ~ '^f45-[a-z0-9-]{12,44}$'),
  action text NOT NULL CHECK(action='claim'),
  recovery_epoch bigint NOT NULL CHECK(recovery_epoch>0),
  observed_at timestamptz NOT NULL DEFAULT clock_timestamp()
);
REVOKE ALL ON perf_f47_authority.observed FROM PUBLIC;

CREATE FUNCTION perf_f47_authority.observe(
  p_token text
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE items text[];
        source_payload text;
        expected_mac text;
        secret_bytes bytea;
        state_name text;
        current_epoch bigint;
        signed_epoch bigint;
        now_seconds bigint;
        issued_seconds bigint;
        expires_seconds bigint;
        found_action text;
        found_epoch bigint;
        found_time bigint;
        inserted text;
BEGIN
  IF p_token IS NULL OR length(p_token)>500 THEN
    RETURN 'invalid_external_permit_denied';
  END IF;

  items:=string_to_array(p_token,'|');
  IF array_length(items,1) IS DISTINCT FROM 10
    OR items[1] IS DISTINCT FROM 'f45'
    OR items[2] NOT IN('claim','ack')
    OR items[3] !~ '^(0|[1-9][0-9]{0,15})$'
    OR items[4] !~ '^worker-f38-[a-z0-9-]{1,20}$'
    OR items[5] !~ '^(0|[1-9][0-9]{0,15})$'
    OR items[6] !~ '^[1-9][0-9]{0,15}$'
    OR items[7] !~ '^[1-9][0-9]{0,15}$'
    OR items[8] !~ '^[1-9][0-9]{0,15}$'
    OR items[9] !~ '^f45-[a-z0-9-]{12,44}$'
    OR items[10] !~ '^[a-f0-9]{64}$'
  THEN RETURN 'invalid_external_permit_denied'; END IF;

  -- No operational "ack" or arbitrary resource operation in this phase.
  IF items[2] IS DISTINCT FROM 'claim'
     OR items[3] IS DISTINCT FROM '0'
     OR items[5] IS DISTINCT FROM '0'
  THEN RETURN 'external_nonread_action_denied'; END IF;

  signed_epoch:=items[6]::bigint;
  issued_seconds:=items[7]::bigint;
  expires_seconds:=items[8]::bigint;
  now_seconds:=floor(extract(epoch from clock_timestamp()))::bigint;

  -- SQL row lock serializes quarantine UPDATE and the ONLY observation
  -- recorded by F47; no stale-bearer acceptance after committed revocation.
  SELECT status,epoch INTO state_name,current_epoch
    FROM perf_f41_recovery.guard_state
    WHERE singleton_id=true FOR SHARE;
  IF NOT FOUND OR state_name IS DISTINCT FROM 'released_mock'
  THEN RETURN 'external_quarantine_denied'; END IF;
  IF signed_epoch IS DISTINCT FROM current_epoch
  THEN RETURN 'stale_external_epoch_denied'; END IF;

  IF expires_seconds IS DISTINCT FROM issued_seconds+30
     OR now_seconds<issued_seconds OR now_seconds>=expires_seconds
  THEN RETURN 'external_expired_permit_denied'; END IF;

  SELECT action,recovery_epoch,issued_at
    INTO found_action,found_epoch,found_time
    FROM perf_f45_issuer.issued
    WHERE nonce=items[9] FOR SHARE;
  IF NOT FOUND OR found_action IS DISTINCT FROM 'claim'
     OR found_epoch IS DISTINCT FROM signed_epoch
     OR found_time IS DISTINCT FROM issued_seconds
  THEN RETURN 'external_issuer_receipt_missing_denied'; END IF;

  SELECT secret INTO secret_bytes FROM perf_f45_issuer.signing_key
    WHERE singleton_id=true;
  IF secret_bytes IS NULL THEN RETURN 'external_issuer_key_unavailable'; END IF;
  source_payload:=array_to_string(items[1:9],'|');
  expected_mac:=encode(public.hmac(
    convert_to(source_payload,'UTF8'),secret_bytes,'sha256'),'hex');
  IF expected_mac IS DISTINCT FROM items[10]
  THEN RETURN 'external_signature_invalid_denied'; END IF;
  -- HMAC equality here is NOT constant-time. Lab only, never production.

  INSERT INTO perf_f47_authority.observed(nonce,action,recovery_epoch)
  VALUES(items[9],'claim',signed_epoch)
  ON CONFLICT (nonce) DO NOTHING
  RETURNING nonce INTO inserted;
  IF inserted IS NULL THEN RETURN 'external_nonce_replay_denied'; END IF;

  -- A bookkeeping observation, NOT execution authorization or dispatch.
  RETURN 'observed_external_mock:'||items[9]||':'||signed_epoch::text;
END;
$$;
REVOKE ALL ON FUNCTION perf_f47_authority.observe(text) FROM PUBLIC;
COMMIT;
