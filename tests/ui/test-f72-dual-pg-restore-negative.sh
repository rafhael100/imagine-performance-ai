#!/usr/bin/env bash
set -euo pipefail
# F1-72: NEGATIVE demonstration of simultaneous restore of both isolated
# PostgreSQL CI services. This exposes a limitation, does not fix it.
test "$APP_ENV" = testing
test "$GITHUB_ACTIONS" = true
test "$PERF_F66_POSTGRES_RLS_LAB" = 1
test "$PERF_F71_INDEPENDENT_RESTORE_LAB" = 1
test "$PERF_F72_DUAL_RESTORE_LAB" = 1
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
primary_admin(){
  PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5436 -U postgres -d perf_f66_ci -A -t -c "$1"
}
primary_a(){
  PGPASSWORD=ci-f66-role-a-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5436 -U perf_f66_tenant_a -d perf_f66_ci -A -t -c "$1"
}
primary_b(){
  PGPASSWORD=ci-f66-role-b-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5436 -U perf_f66_tenant_b -d perf_f66_ci -A -t -c "$1"
}
checkpoint_admin(){
  PGPASSWORD=ci-f71-db-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5437 -U postgres -d perf_f71_ci -A -t -c "$1"
}
checkpoint_a(){
  PGPASSWORD=ci-f71-role-a-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5437 -U perf_f71_tenant_a -d perf_f71_ci -A -t -c "$1"
}
checkpoint_b(){
  PGPASSWORD=ci-f71-role-b-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5437 -U perf_f71_tenant_b -d perf_f71_ci -A -t -c "$1"
}
check(){
  local label="$1" got="$2" expected="$3"
  if [[ "$got" != "$expected" ]]; then
    echo "FAIL F72 $label: expected '$expected', actual '$got'"
    exit 1
  fi
  echo "PASS F72 $label"
}
guard="contracts/lab/php/f71-check-independent-recovery.php"
# F1-71 ended with the PRIMARY restored to F1-70 state. It revoked old
# write EXECUTE on A/B as part of proving fail-closed behavior. Here a CI
# superuser re-enables those LEGACY fixtures ONLY to demonstrate the risk,
# then revokes them again at the end. This is not a new authorized writer.
primary_admin "GRANT EXECUTE ON FUNCTION perf_f66_scope.reserve_campaign(text,text),
 perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)
 TO perf_f66_tenant_a,perf_f66_tenant_b" >/dev/null
check "initial A F70 managed Google campaigns" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed AND channel='google_ads'")" 3
check "initial old F70 campaign still active" \
 "$(primary_a "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late'")" active_demo
check "F71 independent checkpoint still records two newer events" \
 "$(checkpoint_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 2

# Set a CONSISTENT BASELINE in both CI databases: the old F70 reservation
# is still present in primary, and a test-only read marker points to it.
# The F71 newer markers are removed only by the independent DB superuser.
checkpoint_admin "TRUNCATE TABLE perf_f71_guard.checkpoints" >/dev/null
checkpoint_admin "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,channel)
 VALUES('perf_f66_tenant_a','reserve','cam-f70-late','google_ads')" >/dev/null
check "older independent baseline contains one established record" \
 "$(checkpoint_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 1
php "$guard" a a | jq -e '
 .status == "independent_fixture_matches" and
 .checked_checkpoints == 1 and
 .consistent_mock_only == true and
 .ready_for_production == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F72 guard accepts old consistent baseline; NEVER real dispatch"

# Save genuinely old snapshots of BOTH databases at the same baseline.
PGPASSWORD=ci-f66-db-only pg_dump \
 -h 127.0.0.1 -p 5436 -U postgres -d perf_f66_ci \
 --data-only --column-inserts --no-owner --no-privileges \
 --table=perf_f66_scope.capacities \
 --table=perf_f66_scope.campaigns \
 --table=perf_f66_scope.groups \
 --table=perf_f66_scope.swaps > "$tmp/primary-before-f72.sql"
PGPASSWORD=ci-f71-db-only pg_dump \
 -h 127.0.0.1 -p 5437 -U postgres -d perf_f71_ci \
 --data-only --column-inserts --no-owner --no-privileges \
 --table=perf_f71_guard.checkpoints > "$tmp/checkpoint-before-f72.sql"
test -s "$tmp/primary-before-f72.sql"
test -s "$tmp/checkpoint-before-f72.sql"
grep -q cam-f70-late "$tmp/primary-before-f72.sql"
grep -q cam-f70-late "$tmp/checkpoint-before-f72.sql"
echo "PASS F72 genuine pg_dump --data-only snapshots saved for BOTH databases"

check "expand A mock campaign capacity to four" \
 "$(primary_a "SELECT perf_f66_scope.configure_demo('google_ads',4,2)")" capacity_configured_mock
check "new post-backup fake reservation committed" \
 "$(primary_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f72-late')")" included_synthetic_only
check "new post-backup fake swap committed" \
 "$(primary_a "SELECT perf_f66_scope.replace_campaign('req-demo-f72-swap','cam-f70-replacement','cam-f72-new',true,true)")" replaced_synthetic_no_fee
check "new reservation exists" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f72-late' AND managed")" 1
check "new swap receipt exists" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f72-swap'")" 1

checkpoint_admin "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,channel)
 VALUES('perf_f66_tenant_a','reserve','cam-f72-late','google_ads')" >/dev/null
checkpoint_admin "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,old_ref,new_ref)
 VALUES('perf_f66_tenant_a','swap','req-demo-f72-swap','cam-f70-replacement','cam-f72-new')" >/dev/null
check "newer independent marker history contains three records" \
 "$(checkpoint_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 3
php "$guard" a a | jq -e '
 .status == "independent_fixture_matches" and
 .checked_checkpoints == 3 and
 .consistent_mock_only == true and
 .ready_for_production == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F72 three checkpoints match before DUAL stale restore"

# A toy external-world acknowledgment surviving both database restores.
# This plain file is deliberately NOT a trusted immutable authority.
printf 'committed-fixture-reserve=cam-f72-late\ncommitted-fixture-swap=req-demo-f72-swap\n' \
 > "$tmp/outside-both-databases.txt"
markerHash="$(sha256sum "$tmp/outside-both-databases.txt" | cut -d' ' -f1)"
test -n "$markerHash"
echo "PASS F72 toy event record outside both databases"

primary_admin "TRUNCATE perf_f66_scope.swaps,perf_f66_scope.groups,perf_f66_scope.campaigns,perf_f66_scope.capacities RESTART IDENTITY CASCADE" >/dev/null
PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 \
 -h 127.0.0.1 -p 5436 -U postgres -d perf_f66_ci \
 -f "$tmp/primary-before-f72.sql" >/dev/null
echo "PASS F72 primary PostgreSQL restored to older snapshot"
checkpoint_admin "TRUNCATE TABLE perf_f71_guard.checkpoints" >/dev/null
PGPASSWORD=ci-f71-db-only psql -X -q -v ON_ERROR_STOP=1 \
 -h 127.0.0.1 -p 5437 -U postgres -d perf_f71_ci \
 -f "$tmp/checkpoint-before-f72.sql" >/dev/null
echo "PASS F72 independent PostgreSQL ALSO restored to same older snapshot"

check "primary forgot new fake reservation" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f72-late'")" 0
check "primary forgot new fake swap" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f72-swap'")" 0
check "restored old source campaign resurrected" \
 "$(primary_a "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-f70-replacement'")" draft_demo
check "checkpoint DB forgot both newer facts" \
 "$(checkpoint_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 1
check "checkpoint retained only older known fact" \
 "$(checkpoint_a "SELECT ref FROM perf_f71_guard.checkpoints")" cam-f70-late
check "toy outside marker not rolled back" \
 "$(sha256sum "$tmp/outside-both-databases.txt" | cut -d' ' -f1)" "$markerHash"

# CRITICAL EXPECTED FAILURE: F71 checks only surviving restored rows.
# It accepts both stale DBs as 'consistent', though the real-world toy
# marker proves that TWO newer commits existed before the dual restore.
php "$guard" a a | jq -e '
 .status == "independent_fixture_matches" and
 .checked_checkpoints == 1 and
 .consistent_mock_only == true and
 .read_preview_eligible == true and
 .authorization_eligible == false and
 .ready_for_production == false and
 .dispatch_enabled == false and .billing_enabled == false
' >/dev/null
echo "PASS F72 NEGATIVE: simultaneous restore fools F71 consistency guard"
check "B still sees no A checkpoints" \
 "$(checkpoint_b "SELECT count(*) FROM perf_f71_guard.checkpoints")" 0
check "B still cannot select A restored campaign" \
 "$(primary_b "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late'")" 0

# Explicitly revoke legacy writer privileges again in this throwaway CI DB.
primary_admin "REVOKE EXECUTE ON FUNCTION perf_f66_scope.reserve_campaign(text,text),
 perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)
 FROM perf_f66_tenant_a,perf_f66_tenant_b" >/dev/null
if primary_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f72-late')" > "$tmp/bypass" 2>&1; then
 echo "FAIL F72 legacy writer bypass remains after revocation";exit 1
fi
echo "PASS F72 legacy writer test route blocked after negative experiment"
grep -q '"release_state": "blocked"' contracts/lab/ci/performance-release-no-go.json
grep -q '"external_provider_dispatch_allowed": false' contracts/lab/ci/performance-release-no-go.json
echo "PASS F72 production NO-GO: TWO restored DBs can agree while both are stale"
