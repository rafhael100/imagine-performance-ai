CREATE SCHEMA performance_admin_inbox;
REVOKE ALL ON SCHEMA performance_admin_inbox FROM PUBLIC;
CREATE TABLE performance_admin_inbox.receipts (
 issuer text NOT NULL, company_id text NOT NULL, idempotency_key text NOT NULL,
 request_digest char(64) NOT NULL, response jsonb NOT NULL,
 correlation_id text NOT NULL, created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
 PRIMARY KEY(issuer,company_id,idempotency_key)
);
CREATE TABLE performance_admin_inbox.nonces (
 issuer text NOT NULL, nonce text NOT NULL, expires_at bigint NOT NULL,
 PRIMARY KEY(issuer,nonce)
);
-- Service DB role: schema USAGE; SELECT/INSERT only on these tables.
-- No public endpoint, external campaign operation or human token accepted.
