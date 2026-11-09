-- FAST-PERF F1-45: local PostgreSQL 5432 HMAC verifier with private
-- single-use permit receipts and quarantine lock in the SAME transaction.
-- A TEST-ONLY random key is duplicated in each CI DB; production UNFIT.
\set ON_ERROR_STOP on
BEGIN;
CREATE SCHEMA perf_f45_admission;
REVOKE ALL ON SCHEMA perf_f45_admission FROM PUBLIC;
CREATE TABLE perf_f45_admission.verifier_key (
 singleton_id boolean PRIMARY KEY DEFAULT true CHECK(singleton_id),
 secret bytea NOT NULL CHECK(octet_length(secret)=32)
);
INSERT INTO perf_f45_admission.verifier_key
VALUES(true,decode(:'fixture_key','hex'));
REVOKE ALL ON perf_f45_admission.verifier_key FROM PUBLIC;

CREATE TABLE perf_f45_admission.consumed (
 nonce text PRIMARY KEY,
 action text NOT NULL CHECK(action IN('claim','ack')),
 epoch bigint NOT NULL,
 consumed_at bigint NOT NULL
);
REVOKE ALL ON perf_f45_admission.consumed FROM PUBLIC;

CREATE FUNCTION perf_f45_admission.consume(
 p_token text,p_now bigint
) RETURNS text LANGUAGE plpgsql VOLATILE SECURITY DEFINER
SET search_path=pg_catalog AS $$
DECLARE pieces text[];
        payload text;
        signature text;
        secret_bytes bytea;
        action text;
        owner_name text;
        nonce_value text;
        st text;
        epoch_value bigint;
        claimed_epoch bigint;
        issued bigint;
        expiry bigint;
        outbox bigint;
        generation bigint;
        inner_result text;
BEGIN
 IF p_token IS NULL OR length(p_token)>500 OR p_now IS NULL
    OR p_now<1000000000 OR p_now>2100000000
 THEN RETURN 'invalid_signed_permit_denied'; END IF;
 pieces:=string_to_array(p_token,'|');
 IF array_length(pieces,1) IS DISTINCT FROM 10
    OR pieces[1] IS DISTINCT FROM 'f45'
    OR pieces[2] NOT IN('claim','ack')
    OR pieces[3] !~ '^(0|[1-9][0-9]{0,15})$'
    OR pieces[4] !~ '^worker-f38-[a-z0-9-]{1,20}$'
    OR pieces[5] !~ '^(0|[1-9][0-9]{0,15})$'
    OR pieces[6] !~ '^[1-9][0-9]{0,15}$'
    OR pieces[7] !~ '^[1-9][0-9]{0,15}$'
    OR pieces[8] !~ '^[1-9][0-9]{0,15}$'
    OR pieces[9] !~ '^f45-[a-z0-9-]{12,44}$'
    OR pieces[10] !~ '^[a-f0-9]{64}$'
 THEN RETURN 'invalid_signed_permit_denied'; END IF;

 action:=pieces[2];owner_name:=pieces[4];nonce_value:=pieces[9];
 outbox:=pieces[3]::bigint;generation:=pieces[5]::bigint;
 claimed_epoch:=pieces[6]::bigint;
 issued:=pieces[7]::bigint;expiry:=pieces[8]::bigint;
 IF (action='claim' AND (outbox<>0 OR generation<>0))
    OR (action='ack' AND (outbox<=0 OR generation<=0))
    OR expiry IS DISTINCT FROM issued+30
    OR p_now<issued OR p_now>=expiry
 THEN RETURN 'invalid_or_expired_permit_denied'; END IF;

 SELECT secret INTO secret_bytes
 FROM perf_f45_admission.verifier_key WHERE singleton_id=true;
 IF secret_bytes IS NULL THEN RETURN 'permit_verifier_unavailable'; END IF;
 payload:=array_to_string(pieces[1:9],'|');
 signature:=encode(public.hmac(convert_to(payload,'UTF8'),secret_bytes,'sha256'),'hex');
 IF signature IS DISTINCT FROM pieces[10]
 THEN RETURN 'invalid_signature_denied'; END IF;
 -- This comparison is not constant-time: test-only, not production HMAC.

 -- Locks current LOCAL quarantine with the SQL lease operation, so SQL
 -- role cannot bypass the local guard by calling F38/F43 directly.
 SELECT status,epoch INTO st,epoch_value
 FROM perf_f43_sql_guard.state WHERE singleton_id=true FOR SHARE;
 IF NOT FOUND OR st IS DISTINCT FROM 'released_mock'
 THEN RETURN 'sql_quarantine_denied'; END IF;
 IF epoch_value IS DISTINCT FROM claimed_epoch
 THEN RETURN 'stale_local_epoch_denied'; END IF;

 INSERT INTO perf_f45_admission.consumed(nonce,action,epoch,consumed_at)
 VALUES(nonce_value,action,claimed_epoch,p_now)
 ON CONFLICT(nonce) DO NOTHING;
 IF NOT FOUND THEN RETURN 'signed_nonce_replay_denied'; END IF;

 IF action='claim' THEN
    inner_result:=perf_f38_fenced.claim(owner_name,p_now);
    IF inner_result ~ '^claimed_mock:[1-9][0-9]*:[1-9][0-9]*$' THEN
       RETURN 'claimed_signed_mock:'||split_part(inner_result,':',2)
              ||':'||split_part(inner_result,':',3)||':'||claimed_epoch::text;
    END IF;
    RETURN inner_result;
 END IF;

 inner_result:=perf_f38_fenced.ack(outbox,owner_name,generation,p_now);
 IF inner_result='acknowledged_mock' THEN
   RETURN 'acknowledged_signed_mock';
 END IF;
 RETURN inner_result;
END;
$$;
REVOKE ALL ON FUNCTION perf_f45_admission.consume(text,bigint) FROM PUBLIC;
COMMIT;
