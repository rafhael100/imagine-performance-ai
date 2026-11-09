#!/usr/bin/env bash
set -euo pipefail
test "$APP_ENV" = testing
test "$GITHUB_ACTIONS" = true
test "$PERF_F66_POSTGRES_RLS_LAB" = 1
test "$PERF_F71_INDEPENDENT_RESTORE_LAB" = 1
test "$PERF_F73_SIGNED_ANCHOR_LAB" = 1

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
cli="contracts/lab/php/f73-signed-external-anchor-cli.php"
f71="contracts/lab/php/f71-check-independent-recovery.php"
primary_admin(){
  PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5436 -U postgres -d perf_f66_ci -A -t -c "$1"
}
primary_a(){
  PGPASSWORD=ci-f66-role-a-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5436 -U perf_f66_tenant_a -d perf_f66_ci -A -t -c "$1"
}
authority_admin(){
  PGPASSWORD=ci-f71-db-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5437 -U postgres -d perf_f71_ci -A -t -c "$1"
}
authority_a(){
  PGPASSWORD=ci-f71-role-a-only psql -X -q -v ON_ERROR_STOP=1 \
    -h 127.0.0.1 -p 5437 -U perf_f71_tenant_a -d perf_f71_ci -A -t -c "$1"
}
check(){
  local label="$1" got="$2" want="$3"
  if [[ "$got" != "$want" ]]; then
    echo "FAIL F73 $label: $got != $want";exit 1
  fi
  echo "PASS F73 $label"
}
# F72 left the two DBs restored to a baseline checkpoint with no post-F72
# late events. It also revoked the F66 mock writers from A and B.
check "baseline F72 primary event still exists" \
  "$(primary_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f70-late' AND managed")" 1
check "baseline F72 independent checkpoint" \
  "$(authority_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 1
php "$f71" a a | jq -e '
 .status == "independent_fixture_matches" and
 .checked_checkpoints == 1 and .ready_for_production == false
' >/dev/null
echo "PASS F73 F71 guard alone accepts synchronized old baseline"

php "$cli" keys "$tmp/fixture-public.hex" "$tmp/fixture-secret.hex"
test "$(stat -c %a "$tmp/fixture-secret.hex")" = 600
echo 1 > "$tmp/trusted-min-epoch-1.txt"
php "$cli" issue "$tmp/fixture-secret.hex" "$tmp/anchor-epoch-1.json" 1
php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-1.json" \
 "$tmp/trusted-min-epoch-1.txt" a | jq -e '
 .status == "signed_fixture_matches" and
 .signed_epoch == 1 and
 .checked_signed_claims == 1 and
 .matched_signed_fixture == true and
 .authorization_eligible == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F73 signed baseline epoch 1 accepted as fake-only preview"

# Save REAL backups of both PostgreSQL services, NEVER include out-of-DB
# ephemeral public key, signature envelope or monotonic minimum file.
PGPASSWORD=ci-f66-db-only pg_dump \
 -h 127.0.0.1 -p 5436 -U postgres -d perf_f66_ci \
 --data-only --column-inserts --no-owner --no-privileges \
 --table=perf_f66_scope.capacities \
 --table=perf_f66_scope.campaigns \
 --table=perf_f66_scope.groups \
 --table=perf_f66_scope.swaps > "$tmp/primary-old.sql"
PGPASSWORD=ci-f71-db-only pg_dump \
 -h 127.0.0.1 -p 5437 -U postgres -d perf_f71_ci \
 --data-only --column-inserts --no-owner --no-privileges \
 --table=perf_f71_guard.checkpoints > "$tmp/authority-old.sql"
test -s "$tmp/primary-old.sql"
test -s "$tmp/authority-old.sql"
echo "PASS F73 two authentic pg_dump baselines made before new fictional commits"

primary_admin "GRANT EXECUTE ON FUNCTION perf_f66_scope.reserve_campaign(text,text),
 perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)
 TO perf_f66_tenant_a,perf_f66_tenant_b" >/dev/null
check "expand cap for latest mock intent" \
 "$(primary_a "SELECT perf_f66_scope.configure_demo('google_ads',4,2)")" capacity_configured_mock
check "new mock campaign confirmed" \
 "$(primary_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f73-late')")" included_synthetic_only
check "new mock swap confirmed" \
 "$(primary_a "SELECT perf_f66_scope.replace_campaign('req-demo-f73-swap','cam-f70-replacement','cam-f73-new',true,true)")" replaced_synthetic_no_fee
check "primary has new post-backup campaign" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f73-late' AND managed")" 1
check "primary has new post-backup swap" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f73-swap'")" 1
authority_admin "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,channel)
 VALUES('perf_f66_tenant_a','reserve','cam-f73-late','google_ads')" >/dev/null
authority_admin "INSERT INTO perf_f71_guard.checkpoints(tenant_role,kind,ref,old_ref,new_ref)
 VALUES('perf_f66_tenant_a','swap','req-demo-f73-swap','cam-f70-replacement','cam-f73-new')" >/dev/null
check "independent authority now has three fixture records" \
 "$(authority_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 3

php "$cli" issue "$tmp/fixture-secret.hex" "$tmp/anchor-epoch-2.json" 2
echo 2 > "$tmp/trusted-min-epoch-2.txt"
php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-2.json" \
 "$tmp/trusted-min-epoch-2.txt" a | jq -e '
 .status == "signed_fixture_matches" and
 .signed_epoch == 2 and .minimum_epoch == 2 and
 .checked_signed_claims == 3 and
 .matched_signed_fixture == true and
 .authorization_eligible == false and
 .ready_for_production == false and
 .dispatch_enabled == false and .billing_enabled == false
' >/dev/null
echo "PASS F73 latest signed epoch and all three current claims match"

php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-1.json" \
 "$tmp/trusted-min-epoch-2.txt" a | jq -e '
 .status == "signed_epoch_below_external_minimum" and
 .matched_signed_fixture == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F73 validly signed stale epoch 1 rejected by minimum epoch 2"

python3 - "$tmp/anchor-epoch-2.json" "$tmp/tampered-signature.json" <<'PY'
import json,sys
v=json.load(open(sys.argv[1],encoding="utf-8"))
s=v["signature_hex"]
v["signature_hex"]=("0" if s[0]!="0" else "1")+s[1:]
with open(sys.argv[2],"w",encoding="utf-8") as f:
    json.dump(v,f,separators=(",",":"))
PY
php "$cli" check "$tmp/fixture-public.hex" "$tmp/tampered-signature.json" \
 "$tmp/trusted-min-epoch-2.txt" a | jq -e '
 .status == "invalid_signature" and .matched_signed_fixture == false
' >/dev/null
echo "PASS F73 signed envelope corruption denied"

php "$cli" keys "$tmp/wrong-public.hex" "$tmp/wrong-secret.hex"
php "$cli" check "$tmp/wrong-public.hex" "$tmp/anchor-epoch-2.json" \
 "$tmp/trusted-min-epoch-2.txt" a | jq -e '.status == "invalid_signature"' >/dev/null
echo "PASS F73 wrong Ed25519 public key denied"

php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-2.json" \
 "$tmp/trusted-min-epoch-2.txt" b | jq -e '
 .status == "primary_or_independent_guard_denied"
' >/dev/null
echo "PASS F73 tenant B cannot claim signed tenant A checkpoint"
php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-2.json" \
 "$tmp/trusted-min-epoch-2.txt" admin | jq -e '
 .status == "primary_or_independent_guard_denied"
' >/dev/null
echo "PASS F73 primary superuser cannot use tenant signature for a customer"
if APP_ENV=production php "$cli" check "$tmp/fixture-public.hex" \
 "$tmp/anchor-epoch-2.json" "$tmp/trusted-min-epoch-2.txt" a >/dev/null 2>&1; then
  echo "FAIL F73 production accepted fixture verifier";exit 1
fi
echo "PASS F73 signed fixture verifier cannot run in production"

printf 'commit-fixture=cam-f73-late\nreceipt-fixture=req-demo-f73-swap\n' \
 > "$tmp/outside-both-dbs-toy-marker.txt"
markerHash="$(sha256sum "$tmp/outside-both-dbs-toy-marker.txt" | cut -d' ' -f1)"
test -n "$markerHash"
echo "PASS F73 independent toy marker stored outside both PostgreSQL backups"

# BOTH PostgreSQL databases now roll back. The signed newer envelope
# and minimum epoch file remain untouched outside those backups.
primary_admin "TRUNCATE perf_f66_scope.swaps,perf_f66_scope.groups,perf_f66_scope.campaigns,perf_f66_scope.capacities RESTART IDENTITY CASCADE" >/dev/null
PGPASSWORD=ci-f66-db-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
 -p 5436 -U postgres -d perf_f66_ci -f "$tmp/primary-old.sql" >/dev/null
authority_admin "TRUNCATE TABLE perf_f71_guard.checkpoints" >/dev/null
PGPASSWORD=ci-f71-db-only psql -X -q -v ON_ERROR_STOP=1 -h 127.0.0.1 \
 -p 5437 -U postgres -d perf_f71_ci -f "$tmp/authority-old.sql" >/dev/null
echo "PASS F73 both PostgreSQL services genuinely restored to old matching baseline"

check "newer mock campaign disappeared" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.campaigns WHERE ref='cam-f73-late'")" 0
check "newer mock swap disappeared" \
 "$(primary_a "SELECT count(*) FROM perf_f66_scope.swaps WHERE request_ref='req-demo-f73-swap'")" 0
check "checkpoint database lost the two newer facts" \
 "$(authority_a "SELECT count(*) FROM perf_f71_guard.checkpoints")" 1
check "toy marker outside database still proves recent commits" \
 "$(sha256sum "$tmp/outside-both-dbs-toy-marker.txt" | cut -d' ' -f1)" "$markerHash"

php "$f71" a a | jq -e '
 .status == "independent_fixture_matches" and
 .checked_checkpoints == 1 and .consistent_mock_only == true
' >/dev/null
echo "PASS F73 F71 alone incorrectly accepts both stale PostgreSQL databases"

php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-2.json" \
 "$tmp/trusted-min-epoch-2.txt" a | jq -e '
 .status == "signed_claim_count_mismatch" and
 .matched_signed_fixture == false and
 .read_preview_eligible == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F73 surviving epoch-2 signed envelope DETECTS dual stale restore"

php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-1.json" \
 "$tmp/trusted-min-epoch-2.txt" a | jq -e '
 .status == "signed_epoch_below_external_minimum" and
 .matched_signed_fixture == false
' >/dev/null
echo "PASS F73 epoch-1 signed replay denied while external minimum stays 2"

# CRITICAL REMAINING LIMIT: lower the out-of-band minimum (simulating a
# ROLLBACK OF TRUST ROOT). Now the old validly signed envelope is accepted
# against both old DBs. This shows why mutable local files are NOT WORM.
php "$cli" check "$tmp/fixture-public.hex" "$tmp/anchor-epoch-1.json" \
 "$tmp/trusted-min-epoch-1.txt" a | jq -e '
 .status == "signed_fixture_matches" and
 .signed_epoch == 1 and .minimum_epoch == 1 and
 .matched_signed_fixture == true and
 .authorization_eligible == false and .dispatch_enabled == false
' >/dev/null
echo "PASS F73 NEGATIVE: rolling back external minimum ALSO defeats signed guard"

# No authorized writer from this mock gate. Ensure legacy F66 route disabled.
primary_admin "REVOKE EXECUTE ON FUNCTION perf_f66_scope.reserve_campaign(text,text),
 perf_f66_scope.replace_campaign(text,text,text,boolean,boolean)
 FROM perf_f66_tenant_a,perf_f66_tenant_b" >/dev/null
if primary_a "SELECT perf_f66_scope.reserve_campaign('google_ads','cam-f73-late')" > "$tmp/old-writer" 2>&1; then
  echo "FAIL F73 legacy writer still executable";exit 1
fi
echo "PASS F73 original mock writer functions revoked again"
grep -q '"release_state": "blocked"' contracts/lab/ci/performance-release-no-go.json
grep -q '"external_provider_dispatch_allowed": false' contracts/lab/ci/performance-release-no-go.json
echo "PASS F73 NO-GO: Ed25519 + mutable epoch files model recovery but do NOT provide production trust"
