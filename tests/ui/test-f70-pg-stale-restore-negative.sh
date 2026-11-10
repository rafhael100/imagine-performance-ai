#!/usr/bin/env bash
set -euo pipefail

# F1-70: throwaway PostgreSQL CI restore, NEVER run against any customer DB.
test "$APP_ENV" = testing
test "$GITHUB_ACTIONS" = true
test "$PERF_F66_POSTGRES_RLS_LAB" = 1
test "$PERF_F70_STALE_RESTORE_LAB" = 1
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
host=127.0.0.1
port=5436
database=perf_f66_ci
admin(){
  PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 \
    -h "$host" -p "$port" -U postgres -d "$database" -A -t -c "$1"
}
as_a(){
  PGPASSWORD=ci-f66-role-a-only psql -X -q -v ON_ERROR_STOP=1 \
    -h "$host" -p "$port" -U perf_f66_tenant_a -d "$database" -A -t -c "$1"
}
as_b(){
  PGPASSWORD=ci-f66-role-b-only psql -X -q -v ON_ERROR_STOP=1 \
    -h "$host" -p "$port" -U perf_f66_tenant_b -d "$database" -A -t -c "$1"
}
expect(){
  local label="$1" got="$2" want="$3"
  if [[ "$got" != "$want" ]]; then
    echo "FAIL F70 $label: got '$got', expected '$want'"
    exit 1
  fi
  echo "PASS F70 $label"
}

expect "initial A managed Google campaigns" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE channel='google_ads' AND managed")" 2
expect "initial A Google demo cap" \
  "$(as_a "SELECT campaign_limit FROM perf_f66_scope.capacities WHERE channel='google_ads'")" 3
expect "initial B managed campaign" \
  "$(as_b "SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed")" 1
expect "initial A F66 swap exists" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-swap'")" 1
expect "new fake reservation not yet present" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late'")" 0

# Genuine pg_dump --data-only of four test tables. Keep outside the database
# while we write two subsequently committed fake decisions.
PGPASSWORD=ci-f66-db-only pg_dump \
  -h "$host" -p "$port" -U postgres -d "$database" \
  --data-only --column-inserts --no-owner --no-privileges \
  --table=perf_f66_scope.capacities \
  --table=perf_f66_scope.campaigns \
  --table=perf_f66_scope.groups \
  --table=perf_f66_scope.swaps > "$tmp/old-pg-data-only.sql"
test -s "$tmp/old-pg-data-only.sql"
grep -q 'perf_f66_scope.campaigns' "$tmp/old-pg-data-only.sql"
echo "PASS F70 actual pg_dump --data-only saved before new synthetic decisions"

expect "later fake reservation committed" \
  "$(as_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f70-late')")" included_synthetic_only
expect "later fake swap committed" \
  "$(as_a "SELECT perf_f66_scope.replace_campaign('req-demo-f70-swap','cam-a2','cam-f70-replacement',true,true)")" replaced_synthetic_no_fee
expect "new committed fake reservation exists" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late' AND managed")" 1
expect "new committed fake swap receipt exists" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f70-swap'")" 1
expect "managed capacity remains no-overlap" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE channel='google_ads' AND managed")" 3
expect "former A campaign was formally retired" \
  "$(as_a "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-a2'")" outside_scope_demo

# Independent local marker stands in for an *irreversible external fact*.
# This is NOT a provider event or an immutable WORM store.
printf '%s\n' \
  'synthetic-committed-reservation:cam-f70-late' \
  'synthetic-committed-swap:req-demo-f70-swap' \
  > "$tmp/outside-database-acknowledgment.txt"
beforeMarker="$(sha256sum "$tmp/outside-database-acknowledgment.txt" | cut -d' ' -f 1)"
test -n "$beforeMarker"
echo "PASS F70 external toy receipt marker created OUTSIDE PostgreSQL backup"

# Explicit privileged restoration in a disposable CI service. The old backup
# has no rows for the two later committed decisions. Intentionally preserve
# schema, grants, RLS and SQL functions while rolling back table data.
admin "TRUNCATE TABLE perf_f66_scope.swaps,perf_f66_scope.groups,perf_f66_scope.campaigns,perf_f66_scope.capacities RESTART IDENTITY CASCADE" > /dev/null
PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 \
  -h "$host" -p "$port" -U postgres -d "$database" \
  -f "$tmp/old-pg-data-only.sql" >/dev/null
echo "PASS F70 actual PostgreSQL data-only stale restore executed"

expect "later reservation forgotten by restored DB" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late'")" 0
expect "later swap receipt forgotten by restored DB" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f70-swap'")" 0
expect "retired old campaign resurrected in restored DB" \
  "$(as_a "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-a2'")" active_demo
expect "restored A Google used capacity is stale" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE channel='google_ads' AND managed")" 2
expect "F66 original receipt survived same old backup" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-swap'")" 1
expect "B remains isolated and has its original campaign" \
  "$(as_b "SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed")" 1

afterMarker="$(sha256sum "$tmp/outside-database-acknowledgment.txt" | cut -d' ' -f 1)"
expect "toy outside-DB acknowledgment survives stale restore" "$afterMarker" "$beforeMarker"

# Risk: exactly the same committed mock operation IDs become acceptable AGAIN.
# No real Ads calls occur; this is a deliberate negative security proof.
expect "stale DB wrongly accepts replay of original reservation ID" \
  "$(as_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f70-late')")" included_synthetic_only
expect "stale DB wrongly accepts replay of original swap ID" \
  "$(as_a "SELECT perf_f66_scope.replace_campaign('req-demo-f70-swap','cam-a2','cam-f70-replacement',true,true)")" replaced_synthetic_no_fee
expect "still only three current managed campaigns after replay" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE channel='google_ads' AND managed")" 3
expect "B still cannot see replayed A resource" \
  "$(as_b "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late'")" 0
expect "B cannot see replayed A request receipt" \
  "$(as_b "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f70-swap'")" 0

grep -q '"release_state": "blocked"' contracts/lab/ci/performance-release-no-go.json
grep -q '"external_provider_dispatch_allowed": false' contracts/lab/ci/performance-release-no-go.json
echo "PASS F70 intentional negative result: stale restore reopens replay risk; release remains BLOCKED"
