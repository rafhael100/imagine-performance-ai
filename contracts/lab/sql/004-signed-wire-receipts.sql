-- F1-13 - only perf_test_relay_authority in disposable CI.
-- Tracks already accepted local HTTP mock event/nonces across requests.
BEGIN;
CREATE TABLE perf_external_lab.mock_wire_receipts (
    event_id text PRIMARY KEY REFERENCES perf_external_lab.mock_revocation_outbox(event_id),
    key_id text NOT NULL CHECK (key_id='kid-demo-portal-1'),
    nonce text NOT NULL CHECK (nonce ~ '^nonce-demo-[a-z0-9-]{8,68}$'),
    body_sha256 char(64) NOT NULL CHECK (body_sha256 ~ '^[a-f0-9]{64}$'),
    inserted_at timestamptz NOT NULL DEFAULT clock_timestamp(),
    UNIQUE (key_id,nonce)
);
REVOKE ALL ON perf_external_lab.mock_wire_receipts FROM PUBLIC;
GRANT SELECT, INSERT ON perf_external_lab.mock_wire_receipts TO perf_test_outbox_acker;
COMMIT;
