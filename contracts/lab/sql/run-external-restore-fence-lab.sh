#!/usr/bin/env bash
# F1-10 real main pg_dump/pg_restore; second disposable database retains grant fence.
set -euo pipefail
if [[ "$PGHOST" != 127.0.0.1 || "$PGPORT" != 5432
   || "$PGDATABASE" != perf_test_restore_main
   || "$PERF_FENCE_DB" != perf_test_fence_authority
   || "$PGUSER" != postgres || -z "$PGPASSWORD"
   || -z "$PERF_SNAPSHOT_FIXTURE" || -z "$RUNNER_TEMP" ]]; then
  echo 'FAIL refusing non-disposable test DB or unknown host' >&2
  exit 2
fi
export PGOPTIONS='-c statement_timeout=10000'
admin_main() {
  psql -X -q -v ON_ERROR_STOP=1 -d "$PGDATABASE" -c "$1"
}
admin_fence() {
  psql -X -q -v ON_ERROR_STOP=1 -d "$PERF_FENCE_DB" -c "$1"
}
phase() {
  export PERF_RESTORE_PHASE="$1"
  php contracts/lab/php/test-external-restore-fence.php
}

echo '[F1-10] Provisioning TWO DBs in same disposable PostgreSQL service'
createdb -h 127.0.0.1 -U postgres "$PERF_FENCE_DB"
admin_main "CREATE ROLE perf_s2s_lab_caller LOGIN PASSWORD 'ci-test-only-s2s-role' NOSUPERUSER NOCREATEDB NOCREATEROLE"
admin_main "GRANT CONNECT ON DATABASE perf_test_restore_main TO perf_s2s_lab_caller"
admin_fence "CREATE ROLE perf_test_fence_reader LOGIN PASSWORD 'ci-only-fence-reader' NOSUPERUSER NOCREATEDB NOCREATEROLE"
admin_fence "GRANT CONNECT ON DATABASE perf_test_fence_authority TO perf_test_fence_reader"

psql -X -v ON_ERROR_STOP=1 -q -d "$PGDATABASE" -f contracts/lab/sql/001-replay-revocation-lab.sql
psql -X -v ON_ERROR_STOP=1 -q -d "$PERF_FENCE_DB" -f contracts/lab/sql/002-external-restore-fence-authority.sql

admin_main "INSERT INTO perf_s2s_lab.mock_service_grants
(service_ref,organization_ref,tenant_ref,grant_revision,active)
VALUES ('svc-demo-a','org-demo-a','perf-demo-a',3,true),
       ('svc-demo-b','org-demo-b','perf-demo-b',1,true)"
admin_fence "INSERT INTO perf_external_lab.mock_authority_fences
(service_ref,organization_ref,tenant_ref,min_revision,active)
VALUES ('svc-demo-a','org-demo-a','perf-demo-a',3,true),
       ('svc-demo-b','org-demo-b','perf-demo-b',1,true)"
phase baseline

# Real snapshot: contains ACTIVE A grant and one receipt before revocation.
backup="$RUNNER_TEMP/perf-f1-10-pre-revoke.dump"
pg_dump --format=custom --no-acl --no-owner \
  --schema=perf_s2s_lab --dbname="$PGDATABASE" --file="$backup"
test -s "$backup"

# This receipt is created after the snapshot. Restoring erases it.
phase pre_revoke_extra

echo '[F1-10] Revoking A in independent authority and main DB'
admin_fence "UPDATE perf_external_lab.mock_authority_fences
SET active=false,min_revision=4,updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a'"
admin_main "UPDATE perf_s2s_lab.mock_service_grants
SET active=false,grant_revision=4,updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a'"
phase revoked

restore_main_only() {
  echo '[F1-10] Restoring historical main database ONLY'
  admin_main 'DROP SCHEMA perf_s2s_lab CASCADE'
  pg_restore --no-owner --exit-on-error --dbname="$PGDATABASE" "$backup"
  # --no-acl dump: restore restricted role's EXECUTE only, no table access.
  admin_main 'REVOKE ALL ON FUNCTION perf_s2s_lab.mock_claim(text,text,text,integer,text,text,text,text) FROM PUBLIC'
  admin_main 'GRANT USAGE ON SCHEMA perf_s2s_lab TO perf_s2s_lab_caller'
  admin_main 'GRANT EXECUTE ON FUNCTION perf_s2s_lab.mock_claim(text,text,text,integer,text,text,text,text) TO perf_s2s_lab_caller'
}
restore_main_only
phase after_restore

echo '[F1-10] Simulating explicit new fake revision 5 after revoke'
admin_fence "UPDATE perf_external_lab.mock_authority_fences
SET active=true,min_revision=5,updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a'"
admin_main "UPDATE perf_s2s_lab.mock_service_grants
SET active=true,grant_revision=5,updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a'"
phase after_reactivate

restore_main_only
phase second_restore

# If the external fence cannot be read, never fall back to stale local state.
admin_fence 'REVOKE SELECT ON perf_external_lab.mock_authority_fences FROM perf_test_fence_reader'
phase external_unavailable
echo '[PASS F1-10] actual pg_dump/pg_restore denied old grants via separate authority'
