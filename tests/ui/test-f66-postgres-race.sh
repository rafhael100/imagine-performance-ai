#!/usr/bin/env bash
set -euo pipefail
test "$APP_ENV" = testing
test "$PERF_F66_POSTGRES_RLS_LAB" = 1
test "$GITHUB_ACTIONS" = true
worker="contracts/lab/php/f66-pg-concurrent-worker.php"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
test "$(php "$worker" init)" = capacity_configured_mock
pids=()
for i in $(seq 1 16); do
  php "$worker" claim "cam-race-$i" > "$tmp/worker-$i.out" 2> "$tmp/worker-$i.err" &
  pids+=("$!")
done
for pid in "${pids[@]}"; do
  wait "$pid"
done
success=0
denied=0
winner=''
for i in $(seq 1 16); do
  result="$(cat "$tmp/worker-$i.out")"
  case "$result" in
    included_synthetic_only)
      success=$((success+1))
      winner="cam-race-$i"
      ;;
    capacity_review_required)
      denied=$((denied+1))
      ;;
    *)
      echo "FAIL F66 concurrent process result $i = $result"
      cat "$tmp/worker-$i.err"
      exit 1
      ;;
  esac
done
test "$success" -eq 1
test "$denied" -eq 15
echo "PASS F66 exactly 1/16 PostgreSQL writers reserved the last synthetic slot"
php "$worker" count | jq -e '
  .used == 1 and .limit == 1 and
  .session_user == "perf_f66_tenant_a" and
  .billing_enabled == false and
  .dispatch_enabled == false
' >/dev/null
echo "PASS F66 PostgreSQL RLS fixture has no overbooking or provider effects"
test "$(php "$worker" state "$winner")" = active_demo
php "$worker" crash_uncommitted "$winner"
test "$(php "$worker" state "$winner")" = active_demo
echo "PASS F66 interrupted PostgreSQL transaction rolled back"
test "$(php "$worker" pause "$winner")" = paused_synthetic_only
test "$(php "$worker" state "$winner")" = paused_demo
test "$(php "$worker" claim cam-race-after-pause)" = capacity_review_required
echo "PASS F66 pausing the winner did not free its managed slot"
if APP_ENV=production php "$worker" claim cam-race-illegal >/dev/null 2>&1; then
  echo "FAIL F66 accepts production"; exit 1
fi
echo "PASS F66 PostgreSQL concurrent worker refuses production"
