-- F143 fourth disposable Postgres16: live online, not WORM or offsite.
BEGIN;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE SCHEMA perf_f143_oracle;
REVOKE ALL ON SCHEMA perf_f143_oracle FROM PUBLIC;
CREATE TABLE perf_f143_oracle.key_material(
 singleton integer PRIMARY KEY CHECK(singleton=1),
 secret bytea NOT NULL CHECK(octet_length(secret)=32)
);
INSERT INTO perf_f143_oracle.key_material(singleton,secret)
 VALUES(1,decode(:'f142_mac_key','hex'));
CREATE TABLE perf_f143_oracle.floor(
 singleton integer PRIMARY KEY CHECK(singleton=1),
 generation bigint NOT NULL CHECK(generation>=1)
);
INSERT INTO perf_f143_oracle.floor VALUES(1,8);
CREATE TABLE perf_f143_oracle.permits(
 fingerprint text PRIMARY KEY CHECK(fingerprint ~ '^[a-f0-9]{64}$'),
 operation_id text NOT NULL UNIQUE CHECK(operation_id ~ '^[A-Za-z0-9_-]{8,128}$'),
 tenant_ref text NOT NULL CHECK(tenant_ref ~ '^[A-Za-z0-9_-]{8,128}$'),
 generation bigint NOT NULL,
 journal_issued_at bigint NOT NULL,
 journal_sequence bigint NOT NULL,
 journal_head text NOT NULL,
 journal_mac text NOT NULL,
 redeemed boolean NOT NULL DEFAULT false
);
CREATE FUNCTION perf_f143_oracle.issue_if_current(
 p_op text,p_tenant text,p_fp text,p_epoch bigint,
 p_time bigint,p_seq bigint,p_head text,p_mac text
) RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f143_oracle
AS $f143$
DECLARE v_floor bigint; v_secret bytea; v_payload text; v_check text; v_now bigint;
BEGIN
 IF p_op IS NULL OR p_op !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_tenant IS NULL OR p_tenant !~ '^[A-Za-z0-9_-]{8,128}$'
 OR p_fp IS NULL OR p_fp !~ '^[a-f0-9]{64}$'
 OR p_epoch IS NULL OR p_epoch<1 OR p_time IS NULL
 OR p_seq IS NULL OR p_seq<2 OR p_head IS NULL OR p_head !~ '^[a-f0-9]{64}$'
 OR p_mac IS NULL OR p_mac !~ '^[a-f0-9]{64}$' THEN RETURN false; END IF;
 v_now:=floor(extract(epoch from clock_timestamp()))::bigint;
 IF p_time<v_now-30 OR p_time>v_now+5 THEN RETURN false; END IF;
 SELECT generation INTO v_floor FROM perf_f143_oracle.floor
 WHERE singleton=1 FOR SHARE;
 IF v_floor IS NULL OR v_floor<>p_epoch THEN RETURN false; END IF;
 SELECT secret INTO STRICT v_secret FROM perf_f143_oracle.key_material
 WHERE singleton=1;
 v_payload:=format('F142-v1|%s|%s|%s|%s|%s|%s|%s',
  p_op,p_tenant,p_fp,p_epoch,p_time,p_seq,p_head);
 v_check:=encode(public.hmac(convert_to(v_payload,'UTF8'),v_secret,'sha256'),'hex');
 IF v_check<>p_mac THEN RETURN false; END IF;
 INSERT INTO perf_f143_oracle.permits(
  fingerprint,operation_id,tenant_ref,generation,journal_issued_at,
  journal_sequence,journal_head,journal_mac
 ) VALUES (p_fp,p_op,p_tenant,p_epoch,p_time,p_seq,p_head,p_mac)
 ON CONFLICT DO NOTHING;
 RETURN FOUND;
END;
$f143$;

CREATE FUNCTION perf_f143_oracle.guard_redeem()
RETURNS trigger LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f143_oracle
AS $f143$
DECLARE v_floor bigint;
BEGIN
 IF OLD.redeemed OR NEW.redeemed IS DISTINCT FROM TRUE
  OR NEW.fingerprint IS DISTINCT FROM OLD.fingerprint
  OR NEW.operation_id IS DISTINCT FROM OLD.operation_id
  OR NEW.tenant_ref IS DISTINCT FROM OLD.tenant_ref
  OR NEW.generation IS DISTINCT FROM OLD.generation
  OR NEW.journal_issued_at IS DISTINCT FROM OLD.journal_issued_at
  OR NEW.journal_sequence IS DISTINCT FROM OLD.journal_sequence
  OR NEW.journal_head IS DISTINCT FROM OLD.journal_head
  OR NEW.journal_mac IS DISTINCT FROM OLD.journal_mac THEN
   RAISE EXCEPTION 'F143 permit mutation or double redemption prohibited'
     USING ERRCODE='P0001';
 END IF;
 SELECT generation INTO v_floor FROM perf_f143_oracle.floor
  WHERE singleton=1 FOR SHARE;
 IF v_floor IS NULL OR v_floor<>OLD.generation THEN
   RAISE EXCEPTION 'F143 current oracle generation differs'
      USING ERRCODE='P0001';
 END IF;
 RETURN NEW;
END;
$f143$;
CREATE TRIGGER perf_f143_oracle_guard_redeem
 BEFORE UPDATE ON perf_f143_oracle.permits FOR EACH ROW
 EXECUTE FUNCTION perf_f143_oracle.guard_redeem();
CREATE FUNCTION perf_f143_oracle.advance_floor(p_next bigint)
RETURNS boolean LANGUAGE plpgsql SECURITY DEFINER
SET search_path=pg_catalog,perf_f143_oracle
AS $f143$
DECLARE v_floor bigint;
BEGIN
 SELECT generation INTO v_floor FROM perf_f143_oracle.floor
  WHERE singleton=1 FOR UPDATE;
 IF v_floor IS NULL OR p_next IS NULL OR p_next<=v_floor THEN RETURN false; END IF;
 UPDATE perf_f143_oracle.floor SET generation=p_next WHERE singleton=1;
 RETURN true;
END;
$f143$;

REVOKE ALL ON ALL TABLES IN SCHEMA perf_f143_oracle FROM PUBLIC;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA perf_f143_oracle FROM PUBLIC;
GRANT USAGE ON SCHEMA perf_f143_oracle
 TO perf_f143_oracle_issuer,perf_f143_oracle_redeemer;
GRANT EXECUTE ON FUNCTION perf_f143_oracle.issue_if_current(
 text,text,text,bigint,bigint,bigint,text,text)
 TO perf_f143_oracle_issuer;
GRANT SELECT ON perf_f143_oracle.floor TO perf_f143_oracle_issuer;
GRANT SELECT ON perf_f143_oracle.permits TO perf_f143_oracle_redeemer;
GRANT UPDATE(redeemed) ON perf_f143_oracle.permits TO perf_f143_oracle_redeemer;
COMMIT;
