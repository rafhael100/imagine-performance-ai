#!/usr/bin/env bash
set -euo pipefail
test "$APP_ENV" = testing
test "$GITHUB_ACTIONS" = true
test "$PERF_F66_POSTGRES_RLS_LAB" = 1
test "$PERF_F71_INDEPENDENT_RESTORE_LAB" = 1
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
main(){
  PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
    -p 5436 -U postgres -d perf_f66_ci -A -t -c "$1"
}
as_a(){
  PGPASSWORD=ci-f66-role-a-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
    -p 5436 -U perf_f66_tenant_a -d perf_f66_ci -A -t -c "$1"
}
as_b(){
  PGPASSWORD=ci-f66-role-b-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
    -p 5436 -U perf_f66_tenant_b -d perf_f66_ci -A -t -c "$1"
}
authority(){
  PGPASSWORD=ci-f71-db-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
    -p 5437 -U postgres -d perf_f71_ci -A -t -c "$1"
}
authority_a(){
  PGPASSWORD=ci-f71-role-a-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
    -p 5437 -U perf_f71_tenant_a -d perf_f71_ci -A -t -c "$1"
}
authority_b(){
  PGPASSWORD=ci-f71-role-b-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
    -p 5437 -U perf_f71_tenant_b -d perf_f71_ci -A -t -c "$1"
}
guard="contracts/lab/php/f71-check-independent-recovery.php"
check(){
  local label="$1" actual="$2" expected="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL F71 $label actual=$actual expected=$expected"
    exit 1
  fi
  echo "PASS F71 $label"
}
check "primary A session has managed campaigns" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE managed AND channel='google_ads'")" 3
check "checkpoint table initially empty" \
  "$(authority "SELECT count(*) FROM perf_f71_guard.checkpoints")" 0
check "independent reader A starts with empty checkpoints" \
  "$(authority_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 0
check "independent reader B starts with empty checkpoints" \
  "$(authority_b "SELECT count(*) FROM perf_f71_guard.checkpoints")" 0
php "$guard" a a | jq -e '
 .status == "missing_or_oversized_independent_checkpoint" and
 .consistent_mock_only == false and
 .read_preview_eligible == false and
 .dispatch_enabled == false and .billing_enabled == false
' >/dev/null
echo "PASS F71 no independent checkpoint fails closed"
php "$guard" a b | jq -e '
 .status == "identity_or_authority_mismatch" and
 .authorization_eligible == false
' >/dev/null
echo "PASS F71 secondary tenant B cannot masquerade as A"
php "$guard" admin a | jq -e '
 .status == "identity_or_authority_mismatch" and
 .ready_for_production == false
' >/dev/null
echo "PASS F71 primary superuser cannot represent a customer"

if authority_a "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,channel) VALUES('perf_f66_tenant_a','reserve','cam-rogue','google_ads')" > "$tmp/insert-out" 2>&1; then
  echo "FAIL F71 tenant reader inserted checkpoint";exit 1
fi
echo "PASS F71 independent reader cannot INSERT checkpoint"
if authority_b "UPDATE perf_f71_guard.checkpoints SET channel='meta'" > "$tmp/update-out" 2>&1; then
  echo "FAIL F71 tenant reader changed checkpoint";exit 1
fi
echo "PASS F71 independent reader cannot UPDATE checkpoint"

# Back up only the primary DB's F66 business fixture tables, never the separate authority.
PGPASSWORD=ci-f66-db-only pg_dump -h 127.0.0.1 -p 5436 -U postgres \
  -d perf_f66_ci --data-only --column-inserts --no-owner --no-privileges \
  --table=perf_f66_scope.capacities \
  --table=perf_f66_scope.campaigns \
  --table=perf_f66_scope.groups \
  --table=perf_f66_scope.swaps > "$tmp/f71-old-primary.sql"
test -s "$tmp/f71-old-primary.sql"
echo "PASS F71 actual PostgreSQL primary stale snapshot saved"

check "increase fixture campaign capacity" \
  "$(as_a "SELECT perf_f66_scope.configure_demo('google_ads',4,2)")" capacity_configured_mock
check "commit a new mock reservation" \
  "$(as_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f71-late')")" included_synthetic_only
check "commit a new mock swap" \
  "$(as_a "SELECT perf_f66_scope.replace_campaign('req-demo-f71-swap','cam-f70-replacement','cam-f71-new',true,true)")" replaced_synthetic_no_fee
check "new mock reservation exists before restore" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f71-late' AND managed")" 1
check "new mock swap exists before restore" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f71-swap'")" 1
check "new mock swapped out original" \
  "$(as_a "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-f70-replacement'")" outside_scope_demo
check "managed quota after new fake commits" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE channel='google_ads' AND managed")" 4

# These are synthetic checkpoints written by the ADMIN of the independent
# PostgreSQL container, ONLY AFTER the PRIMARY mock commits. Real systems
# would require an atomic or recoverable protocol, which is not implemented.
authority "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,channel)
  VALUES('perf_f66_tenant_a','reserve','cam-f71-late','google_ads')" > /dev/null
authority "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,old_ref,new_ref)
  VALUES('perf_f66_tenant_a','swap','req-demo-f71-swap','cam-f70-replacement','cam-f71-new')" > /dev/null
check "independent checkpoint A has two confirmed facts" \
  "$(authority_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 2
check "independent checkpoint B sees zero A facts via RLS" \
  "$(authority_b "SELECT count(*) FROM perf_f71_guard.checkpoints")" 0
php "$guard" a a | jq -e '
 .schema_version == "perf.recovery.guard.synthetic.v1" and
 .status == "independent_fixture_matches" and
 .tenant_role == "perf_f66_tenant_a" and
 .checked_checkpoints == 2 and
 .consistent_mock_only == true and
 .read_preview_eligible == true and
 .authorization_eligible == false and
 .ready_for_production == false and
 .dispatch_enabled == false and
 .billing_enabled == false
' >/dev/null
echo "PASS F71 independent PostgreSQL matches two current commits (read preview ONLY)"

php "$guard" b b | jq -e '
 .status == "missing_or_oversized_independent_checkpoint" and
 .consistent_mock_only == false and
 .read_preview_eligible == false
' >/dev/null
echo "PASS F71 B cannot borrow A checkpoints"

# Real rollback of primary fixture tables only. Independent authority survives.
main "TRUNCATE perf_f66_scope.swaps,perf_f66_scope.groups,perf_f66_scope.campaigns,perf_f66_scope.capacities RESTART IDENTITY CASCADE" >/dev/null
PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
  -p 5436 -U postgres -d perf_f66_ci -f "$tmp/f71-old-primary.sql" >/dev/null
echo "PASS F71 genuine primary stale restore, independent authority not restored"
check "restored primary lost confirmed reservation" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f71-late'")" 0
check "restored primary lost confirmed swap" \
  "$(as_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f71-swap'")" 0
check "restored primary resurrected source campaign" \
  "$(as_a "SELECT state FROM perf_f66_scope.campaigns WHERE ref='cam-f70-replacement'")" draft_demo
check "external database still retains two checkpoints" \
  "$(authority_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 2
php "$guard" a a | jq -e '
 .status == "missing_or_changed_committed_reservation" and
 .consistent_mock_only == false and
 .read_preview_eligible == false and
 .authorization_eligible == false and
 .ready_for_production == false and
 .dispatch_enabled == false and
 .billing_enabled == false
' >/dev/null
echo "PASS F71 independent guard DENIES preview after primary stale restore"

# First ensure that an unavailable authority is not silently ignored.
authority "REVOKE SELECT ON perf_f71_guard.checkpoints FROM perf_f71_tenant_a" >/dev/null
php "$guard" a a | jq -e '
 .status == "checkpoint_read_or_sql_denied" and
 .read_preview_eligible == false and
 .dispatch_enabled == false
' >/dev/null
echo "PASS F71 missing independent SELECT privilege denies"
authority "GRANT SELECT ON perf_f71_guard.checkpoints TO perf_f71_tenant_a" >/dev/null
php "$guard" a b | jq -e '.status == "identity_or_authority_mismatch"' >/dev/null
echo "PASS F71 cross-database identity mismatch still denied after restore"

# CRITICAL: F66 legacy stored procedures CAN bypass this read-only guard.
# The test now REVOKES their EXECUTE privileges from fixture roles, proving
# directly invoking legacy write functions is denied. No replacement writer
# is authorized by F71. A production revoke design is still P0/NO-GO.
main "REVOKE EXECUTE ON FUNCTION perf_f66_scope.reserve_campaign(text,text),
  perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)
  FROM perf_f66_tenant_a,perf_f66_tenant_b" >/dev/null
if as_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f71-late')" > "$tmp/legacy" 2>&1; then
  echo "FAIL F71 legacy reservation remains executable after revocation";exit 1
fi
echo "PASS F71 revoked direct legacy reservation bypass after regression tests"
if as_a "SELECT perf_f66_scope.replace_campaign('req-demo-f71-swap','cam-f70-replacement','cam-f71-new',true,true)" > "$tmp/legacy-swap" 2>&1; then
  echo "FAIL F71 legacy swap remains executable after revocation";exit 1
fi
echo "PASS F71 revoked direct legacy swap bypass after regression tests"
if as_b "SELECT perf_f66_scope.reserve_campaign('meta','cam-f71-foreign')" > "$tmp/legacy-b" 2>&1; then
  echo "FAIL F71 B still has legacy execute";exit 1
fi
echo "PASS F71 revoked B legacy write bypass"
php "$guard" a a | jq -e '
 .status == "missing_or_changed_committed_reservation" and
 .consistent_mock_only == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F71 fail-closed state persists with old write route disabled"

grep -q '"release_state": "blocked"' contracts/lab/ci/performance-release-no-go.json
grep -q '"external_provider_dispatch_allowed": false' contracts/lab/ci/performance-release-no-go.json
echo "PASS F71 NO-GO: two DBs can disagree; never claim restored customer safety"
