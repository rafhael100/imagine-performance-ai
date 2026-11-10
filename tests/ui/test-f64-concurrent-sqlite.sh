#!/usr/bin/env bash
set -euo pipefail
test "$APP_ENV" = testing
test "$PERF_F64_SQLITE_SCOPE_LAB" = 1
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
export F64_DB_PATH="$tmp/perf-f64-race.sqlite"
worker="contracts/lab/php/f64-sqlite-concurrent-worker.php"
php "$worker" init | grep -qx 'capacity_configured_demo'
pids=()
for i in $(seq 1 16); do
  php "$worker" claim "cam-race-$i" > "$tmp/result-$i.txt" 2> "$tmp/error-$i.txt" &
  pids+=("$!")
done
for pid in "${pids[@]}"; do
  wait "$pid"
done
winners=0
losers=0
winner=''
for i in $(seq 1 16); do
  result="$(cat "$tmp/result-$i.txt")"
  case "$result" in
    included_synthetic_only)
      winners=$((winners+1))
      winner="cam-race-$i"
      ;;
    capacity_review_required)
      losers=$((losers+1))
      ;;
    *)
      echo "FAIL F64 unexpected concurrent result '$result' in worker $i"
      cat "$tmp/error-$i.txt"
      exit 1
      ;;
  esac
done
test "$winners" -eq 1
test "$losers" -eq 15
echo "PASS F64 16 processes race for last slot: one winner, 15 denials"
capacity="$(php "$worker" capacity)"
echo "$capacity" | jq -e '
  .status == "configured_synthetic_only" and
  .campaigns.used == 1 and
  .campaigns.limit == 1 and
  .campaigns.remaining == 0 and
  .dispatch_enabled == false and
  .billing_enabled == false
' >/dev/null
echo "PASS F64 durable winner and no overbooking"
test "$(php "$worker" state "$winner")" = active_demo
php "$worker" crash_uncommitted "$winner"
test "$(php "$worker" state "$winner")" = active_demo
echo "PASS F64 simulated crash before commit rolls back SQLite state"
test "$(php "$worker" pause "$winner")" = paused_synthetic_only
test "$(php "$worker" state "$winner")" = paused_demo
test "$(php "$worker" claim cam-race-after-pause)" = capacity_review_required
echo "PASS F64 pause does not free last managed slot"
if APP_ENV=production php "$worker" claim cam-race-illegal >/dev/null 2>&1; then
  echo "FAIL F64 worker accepted production"; exit 1
fi
echo "PASS F64 production worker refused"
