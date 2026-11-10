#!/usr/bin/env bash
# Disposable PostgreSQL-only test; the lab user has EXECUTE but no table grants.
set -euo pipefail
: "$PGHOST" "$PGPORT" "$PGDATABASE" "$PGPASSWORD"
[[ "$PGDATABASE" =~ ^perf_test_[a-zA-Z0-9_]+$ ]] || { echo "FAIL non-disposable DB"; exit 2; }
[[ "$PGHOST" = 127.0.0.1 && "$PGPORT" = 5432 ]] || { echo "FAIL non-loopback DB"; exit 2; }

ADMIN_PASSWORD="$PGPASSWORD"
LAB_PASSWORD='ci-test-only-s2s-role'
DIGEST="$(printf '%064d' 0)"
count=0

admin() {
  PGPASSWORD="$ADMIN_PASSWORD" PGUSER=postgres psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"
}
caller() {
  PGPASSWORD="$LAB_PASSWORD" PGUSER=perf_s2s_lab_caller psql -X -A -t -q -v ON_ERROR_STOP=1 -c "$1"
}
claim() {
  local org="$1" tenant="$2" svc="$3" rev="$4" request="$5" nonce="$6"
  local digest="$DIGEST" producer="portal-imagine-mock"
  if [[ "$#" -ge 7 ]]; then digest="$7"; fi
  if [[ "$#" -ge 8 ]]; then producer="$8"; fi
  printf "SELECT perf_s2s_lab.mock_claim('%s','%s','%s',%s,'%s','%s','%s','%s');" \
    "$org" "$tenant" "$svc" "$rev" "$producer" "$request" "$nonce" "$digest"
}
check() {
  local name="$1" expected="$2" stmt="$3" actual
  actual="$(caller "$stmt" | tr -d '\r\n')"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL $name expected [$expected] got [$actual]" >&2
    exit 1
  fi
  count=$((count+1))
  echo "PASS $count $name"
}
check_admin() {
  local name="$1" expected="$2" stmt="$3" actual
  actual="$(admin "$stmt" | tr -d '\r\n')"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL $name expected [$expected] got [$actual]" >&2
    exit 1
  fi
  count=$((count+1))
  echo "PASS $count $name"
}
deny_sql() {
  local name="$1" stmt="$2"
  if caller "$stmt" >/dev/null 2>&1; then
    echo "FAIL $name: SQL unexpectedly permitted" >&2
    exit 1
  fi
  count=$((count+1))
  echo "PASS $count $name"
}

admin "INSERT INTO perf_s2s_lab.mock_service_grants
(service_ref,organization_ref,tenant_ref,grant_revision,active)
VALUES ('svc-demo-a','org-demo-a','perf-demo-a',3,true),
       ('svc-demo-b','org-demo-b','perf-demo-b',1,true);" >/dev/null

check '01 accept tenant A in one DB session' accepted_synthetic_only \
  "$(claim org-demo-a perf-demo-a svc-demo-a 3 req-demo-a1 nonce-demo-a1)"
check '02 duplicate request after reconnect' mock_replay_denied \
  "$(claim org-demo-a perf-demo-a svc-demo-a 3 req-demo-a1 nonce-demo-a2)"
check '03 duplicate nonce after reconnect' mock_replay_denied \
  "$(claim org-demo-a perf-demo-a svc-demo-a 3 req-demo-a2 nonce-demo-a1)"
check_admin '04 exactly one receipt committed' 1 \
  "SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts;"
check '05 tenant B cannot claim A service' not_found \
  "$(claim org-demo-b perf-demo-b svc-demo-a 3 req-demo-bad1 nonce-demo-bad1)"
check '06 foreign organization cannot claim A service' not_found \
  "$(claim org-demo-b perf-demo-a svc-demo-a 3 req-demo-bad2 nonce-demo-bad2)"
check '07 outdated revision denied' mock_grant_revision_stale \
  "$(claim org-demo-a perf-demo-a svc-demo-a 2 req-demo-bad3 nonce-demo-bad3)"
check '08 absent service denied' not_found \
  "$(claim org-demo-a perf-demo-a svc-demo-absent 3 req-demo-bad4 nonce-demo-bad4)"
check '09 wrong producer denied' invalid_lab_input \
  "$(claim org-demo-a perf-demo-a svc-demo-a 3 req-demo-bad5 nonce-demo-bad5 "$DIGEST" untrusted)"
check '10 independent tenant B valid' accepted_synthetic_only \
  "$(claim org-demo-b perf-demo-b svc-demo-b 1 req-demo-b1 nonce-demo-b1)"
check '11 global request replay cross-tenant denied' mock_replay_denied \
  "$(claim org-demo-b perf-demo-b svc-demo-b 1 req-demo-a1 nonce-demo-b2)"
check '12 global nonce replay cross-tenant denied' mock_replay_denied \
  "$(claim org-demo-b perf-demo-b svc-demo-b 1 req-demo-b2 nonce-demo-a1)"
deny_sql '13 runtime cannot read grant table' \
  "SELECT count(*) FROM perf_s2s_lab.mock_service_grants;"
deny_sql '14 runtime cannot read replay receipts' \
  "SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts;"
deny_sql '15 runtime cannot directly revoke' \
  "UPDATE perf_s2s_lab.mock_service_grants SET active=false WHERE service_ref='svc-demo-a';"
deny_sql '16 runtime cannot erase replay receipts' \
  "DELETE FROM perf_s2s_lab.mock_replay_receipts;"
check_admin '17 only two receipts persisted' 2 \
  "SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts;"

admin "UPDATE perf_s2s_lab.mock_service_grants
SET active=false, grant_revision=4, updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a';" >/dev/null
check '18 revoked grant denies new message' mock_grant_revoked \
  "$(claim org-demo-a perf-demo-a svc-demo-a 4 req-demo-a3 nonce-demo-a3)"
check '19 old revision still revoked' mock_grant_revoked \
  "$(claim org-demo-a perf-demo-a svc-demo-a 3 req-demo-a4 nonce-demo-a4)"
admin "UPDATE perf_s2s_lab.mock_service_grants
SET active=true, grant_revision=5, updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a';" >/dev/null
check '20 reactivation rejects old revision' mock_grant_revision_stale \
  "$(claim org-demo-a perf-demo-a svc-demo-a 3 req-demo-a5 nonce-demo-a5)"
check '21 new grant revision accepts only mock' accepted_synthetic_only \
  "$(claim org-demo-a perf-demo-a svc-demo-a 5 req-demo-a5 nonce-demo-a5)"
check '22 replay survives reactivation' mock_replay_denied \
  "$(claim org-demo-a perf-demo-a svc-demo-a 5 req-demo-a1 nonce-demo-replay)"
check_admin '23 exactly three receipts after revocation' 3 \
  "SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts;"
check_admin '24 unrelated tenant retains own revision' 1 \
  "SELECT grant_revision FROM perf_s2s_lab.mock_service_grants WHERE service_ref='svc-demo-b';"

# A rolled-back provisional receipt does not consume the globally unique ID.
admin "BEGIN;
INSERT INTO perf_s2s_lab.mock_replay_receipts
(producer,request_id,nonce,service_ref,tenant_ref,grant_revision,payload_sha256)
VALUES ('portal-imagine-mock','req-demo-rolledback','nonce-demo-rolledback',
        'svc-demo-a','perf-demo-a',5,'$DIGEST');
ROLLBACK;" >/dev/null
check '25 rollback does not create a durable receipt' accepted_synthetic_only \
  "$(claim org-demo-a perf-demo-a svc-demo-a 5 req-demo-rolledback nonce-demo-rolledback)"
check '26 replay after successful retry denied' mock_replay_denied \
  "$(claim org-demo-a perf-demo-a svc-demo-a 5 req-demo-rolledback nonce-demo-next)"
check_admin '27 final receipt count matches accepted messages' 4 \
  "SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts;"

echo "PASS $count isolated PostgreSQL replay/revocation assertions"
