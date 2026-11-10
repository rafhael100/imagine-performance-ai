-- F125 EXTERNAL PostgreSQL16 witness: a second independently running CI
-- service at host loopback port 5433, with database perf_f125_witness_ci.
-- Not a production authority or durable offsite service. A backup restore
-- of primary port 5432 does not revert this service *within this test*.
-- No passwords, access to primary DB, customer data or Ads are used.

BEGIN;

CREATE ROLE perf_f125_witness_owner NOLOGIN NOSUPERUSER NOCREATEDB
 NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION;

CREATE ROLE perf_f125_witness_writer LOGIN
 PASSWORD 'ci-f125-witness-insert-only'
 NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS NOREPLICATION;

CREATE TABLE public.perf_f125_signed_portal_witness (
 nonce_digest char(64) PRIMARY KEY,
 claimed_epoch bigint NOT NULL CHECK (
   claimed_epoch >= 1700000000 AND claimed_epoch <= 2100000000
 ),
 CONSTRAINT f125_nonce_digest_hex CHECK (
   nonce_digest ~ '^[a-f0-9]{64}$'
 )
);

ALTER TABLE public.perf_f125_signed_portal_witness
 OWNER TO perf_f125_witness_owner;

ALTER TABLE public.perf_f125_signed_portal_witness
 ENABLE ROW LEVEL SECURITY;

ALTER TABLE public.perf_f125_signed_portal_witness
 FORCE ROW LEVEL SECURITY;

REVOKE ALL ON TABLE public.perf_f125_signed_portal_witness FROM PUBLIC;

GRANT CONNECT ON DATABASE perf_f125_witness_ci
 TO perf_f125_witness_writer;

GRANT USAGE ON SCHEMA public TO perf_f125_witness_writer;

GRANT INSERT ON TABLE public.perf_f125_signed_portal_witness
 TO perf_f125_witness_writer;

CREATE POLICY f125_witness_insert_only
 ON public.perf_f125_signed_portal_witness
 AS PERMISSIVE
 FOR INSERT
 TO perf_f125_witness_writer
 WITH CHECK (true);

COMMIT;
