#!/usr/bin/env bash
# F1-11 real PostgreSQL lab: simulated Portal authority/outbox is a second
# disposable DB in the SAME test cluster, NOT a real independent service.
set -euo pipefail
if [[ "$PGHOST" != "127.0.0.1" || "$PGPORT" != "5432"
   || "$PGDATABASE" != "perf_test_relay_main"
   || "$PERF_FENCE_DB" != "perf_test_relay_authority"
   || "$PGUSER" != "postgres" || -z "$PGPASSWORD"
   || -z "$PERF_SNAPSHOT_FIXTURE" || -z "$RUNNER_TEMP" ]]; then
  echo "FAIL: non-disposable DB or external host refused" >&2
  exit 2
fi
export PGOPTIONS="-c statement_timeout=10000"
main() {
  psql -X -v ON_ERROR_STOP=1 -q -d "$PGDATABASE" -c "$1"
}
authority() {
  psql -X -v ON_ERROR_STOP=1 -q -d "$PERF_FENCE_DB" -c "$1"
}
phase() {
  export PERF_RELAY_PHASE="$1"
  php contracts/lab/php/test-revocation-relay-phases.php
}
echo "[F1-11] Two separate databases inside disposable runner, no external services"
createdb -h 127.0.0.1 -U postgres "$PERF_FENCE_DB"

# Roles are test-only and limited to their responsibility.
main "CREATE ROLE perf_s2s_lab_caller LOGIN PASSWORD 'ci-test-only-s2s-role' NOSUPERUSER NOCREATEDB NOCREATEROLE"
main "GRANT CONNECT ON DATABASE perf_test_relay_main TO perf_s2s_lab_caller"
main "CREATE ROLE perf_test_main_revoker LOGIN PASSWORD 'ci-only-main-revoker' NOSUPERUSER NOCREATEDB NOCREATEROLE"
main "GRANT CONNECT ON DATABASE perf_test_relay_main TO perf_test_main_revoker"
authority "CREATE ROLE perf_test_fence_reader LOGIN PASSWORD 'ci-only-fence-reader' NOSUPERUSER NOCREATEDB NOCREATEROLE"
authority "GRANT CONNECT ON DATABASE perf_test_relay_authority TO perf_test_fence_reader"
authority "CREATE ROLE perf_test_outbox_acker LOGIN PASSWORD 'ci-only-outbox-acker' NOSUPERUSER NOCREATEDB NOCREATEROLE"
authority "GRANT CONNECT ON DATABASE perf_test_relay_authority TO perf_test_outbox_acker"

psql -X -v ON_ERROR_STOP=1 -q -d "$PGDATABASE" -f contracts/lab/sql/001-replay-revocation-lab.sql
psql -X -v ON_ERROR_STOP=1 -q -d "$PERF_FENCE_DB" -f contracts/lab/sql/002-external-restore-fence-authority.sql
psql -X -v ON_ERROR_STOP=1 -q -d "$PERF_FENCE_DB" -f contracts/lab/sql/003-portal-revocation-outbox-lab.sql

grant_main_revoker() {
  main "GRANT USAGE ON SCHEMA perf_s2s_lab TO perf_test_main_revoker"
  main "GRANT SELECT ON perf_s2s_lab.mock_service_grants TO perf_test_main_revoker"
  main "GRANT UPDATE(active, grant_revision, updated_at) ON perf_s2s_lab.mock_service_grants TO perf_test_main_revoker"
}
grant_main_revoker

main "INSERT INTO perf_s2s_lab.mock_service_grants
(service_ref,organization_ref,tenant_ref,grant_revision,active)
VALUES ('svc-demo-a','org-demo-a','perf-demo-a',3,true),
       ('svc-demo-b','org-demo-b','perf-demo-b',1,true)"
authority "INSERT INTO perf_external_lab.mock_authority_fences
(service_ref,organization_ref,tenant_ref,min_revision,active)
VALUES ('svc-demo-a','org-demo-a','perf-demo-a',3,true),
       ('svc-demo-b','org-demo-b','perf-demo-b',1,true)"
phase baseline

# Dump a real snapshot from before the first revocation. Only MAIN is backed up.
backup="$RUNNER_TEMP/perf-f1-11-old-main.dump"
pg_dump --format=custom --no-acl --no-owner \
  --schema=perf_s2s_lab --dbname="$PGDATABASE" --file="$backup"
test -s "$backup"

echo "[F1-11] Portal authority simulates atomic revoke + outbox commit"
authority "BEGIN;
UPDATE perf_external_lab.mock_authority_fences
SET active=false, min_revision=4, updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a' AND min_revision=3 AND active=true;
INSERT INTO perf_external_lab.mock_revocation_outbox
(event_id,service_ref,organization_ref,tenant_ref,source_revision,event_type)
VALUES ('evt-demo-revoke-4','svc-demo-a','org-demo-a','perf-demo-a',4,
        'service.authorization_changed.synthetic');
COMMIT;"
phase pending
phase delivered

echo "[F1-11] Source explicitly reactivates at a NEW revision"
authority "BEGIN;
UPDATE perf_external_lab.mock_authority_fences
SET active=true, min_revision=5, updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a' AND min_revision=4 AND active=false;
INSERT INTO perf_external_lab.mock_revocation_outbox
(event_id,service_ref,organization_ref,tenant_ref,source_revision,event_type)
VALUES ('evt-demo-activate-5','svc-demo-a','org-demo-a','perf-demo-a',5,
        'service.authorization_changed.synthetic');
COMMIT;"
phase reactivated

echo "[F1-11] New revocation arrives before old event is re-delivered"
authority "BEGIN;
UPDATE perf_external_lab.mock_authority_fences
SET active=false, min_revision=6, updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a' AND min_revision=5 AND active=true;
INSERT INTO perf_external_lab.mock_revocation_outbox
(event_id,service_ref,organization_ref,tenant_ref,source_revision,event_type)
VALUES ('evt-demo-revoke-6','svc-demo-a','org-demo-a','perf-demo-a',6,
        'service.authorization_changed.synthetic');
COMMIT;"
phase second_revoke

echo "[F1-11] Restore old MAIN only; Portal authority DB remains untouched"
main "DROP SCHEMA perf_s2s_lab CASCADE"
pg_restore --no-owner --exit-on-error --dbname="$PGDATABASE" "$backup"
main "REVOKE ALL ON FUNCTION perf_s2s_lab.mock_claim(text,text,text,integer,text,text,text,text) FROM PUBLIC"
main "GRANT USAGE ON SCHEMA perf_s2s_lab TO perf_s2s_lab_caller"
main "GRANT EXECUTE ON FUNCTION perf_s2s_lab.mock_claim(text,text,text,integer,text,text,text,text) TO perf_s2s_lab_caller"
grant_main_revoker
phase restored

echo "[F1-11] Simulate loss of authority access; both relayer and claims must deny"
authority "REVOKE SELECT ON perf_external_lab.mock_revocation_outbox FROM perf_test_fence_reader"
authority "REVOKE SELECT ON perf_external_lab.mock_authority_fences FROM perf_test_fence_reader"
phase authority_unavailable

echo "[PASS F1-11] outbox retry, reordered notification, failed ACK, restore and fail-closed checks"
