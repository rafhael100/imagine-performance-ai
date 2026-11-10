#!/usr/bin/env bash
# Two PostgreSQL sessions: hold a grant row lock while another session tries a
# fake claim, then confirm that the committed revocation wins.
set -euo pipefail
: "$PGHOST" "$PGPORT" "$PGDATABASE" "$PGPASSWORD"
[[ "$PGHOST" = 127.0.0.1 && "$PGPORT" = 5432 && "$PGDATABASE" = perf_test_s2s_ci ]] || exit 2
ADMIN_PASSWORD="$PGPASSWORD"
LAB_PASSWORD="ci-test-only-s2s-role"
LOG="$(mktemp)"
REVOKE_PID=""
cleanup() {
  if [[ -n "$REVOKE_PID" ]] && kill -0 "$REVOKE_PID" >/dev/null 2>&1; then
    kill "$REVOKE_PID" || true
    wait "$REVOKE_PID" || true
  fi
  rm -f "$LOG"
}
trap cleanup EXIT

(
  PGAPPNAME=perf_fence_revoke_ci PGPASSWORD="$ADMIN_PASSWORD" PGUSER=postgres \
    psql -X -q -v ON_ERROR_STOP=1 <<'SQL'
BEGIN;
UPDATE perf_s2s_lab.mock_service_grants
SET active=false, grant_revision=6, updated_at=clock_timestamp()
WHERE service_ref='svc-demo-a';
SELECT pg_sleep(3);
COMMIT;
SQL
) >"$LOG" 2>&1 &
REVOKE_PID="$!"

seen=0
for i in $(seq 1 35); do
  activity="$(PGPASSWORD="$ADMIN_PASSWORD" PGUSER=postgres psql -X -A -t -q -v ON_ERROR_STOP=1 -c \
    "SELECT count(*) FROM pg_stat_activity WHERE application_name='perf_fence_revoke_ci' AND wait_event='PgSleep';" | tr -d '\r\n')"
  if [[ "$activity" = 1 ]]; then
    seen=1
    break
  fi
  if ! kill -0 "$REVOKE_PID" >/dev/null 2>&1; then
    echo "FAIL: revocation transaction terminated before lock synchronization" >&2
    cat "$LOG" >&2
    exit 1
  fi
  sleep 0.1
done
if [[ "$seen" -ne 1 ]]; then
  echo "FAIL: could not confirm revocation transaction holds lock" >&2
  cat "$LOG" >&2
  exit 1
fi

# This claim blocks at FOR UPDATE until the revocation commits. It may not
# succeed on the old grant revision and must not create a replay receipt.
digest="$(printf '%064d' 0)"
result="$(PGOPTIONS='-c statement_timeout=10000' PGPASSWORD="$LAB_PASSWORD" PGUSER=perf_s2s_lab_caller \
  psql -X -A -t -q -v ON_ERROR_STOP=1 -c \
  "SELECT perf_s2s_lab.mock_claim('org-demo-a','perf-demo-a','svc-demo-a',5,
  'portal-imagine-mock','req-demo-race','nonce-demo-race','$digest');" | tr -d '\r\n')"
wait "$REVOKE_PID"
REVOKE_PID=""
if [[ "$result" != mock_grant_revoked ]]; then
  echo "FAIL: raced claim returned $result instead of mock_grant_revoked" >&2
  cat "$LOG" >&2
  exit 1
fi
echo "PASS 28 revocation row lock serializes competing message"

receipts="$(PGPASSWORD="$ADMIN_PASSWORD" PGUSER=postgres psql -X -A -t -q -v ON_ERROR_STOP=1 -c \
  "SELECT count(*) FROM perf_s2s_lab.mock_replay_receipts WHERE request_id='req-demo-race';" | tr -d '\r\n')"
if [[ "$receipts" != 0 ]]; then
  echo "FAIL: revoked raced request created receipt" >&2
  exit 1
fi
echo "PASS 29 denied race does not consume replay ID"

PGPASSWORD="$ADMIN_PASSWORD" PGUSER=postgres psql -X -q -v ON_ERROR_STOP=1 -c \
  "UPDATE perf_s2s_lab.mock_service_grants SET active=true, grant_revision=7
   WHERE service_ref='svc-demo-a';" >/dev/null
result="$(PGPASSWORD="$LAB_PASSWORD" PGUSER=perf_s2s_lab_caller \
  psql -X -A -t -q -v ON_ERROR_STOP=1 -c \
  "SELECT perf_s2s_lab.mock_claim('org-demo-a','perf-demo-a','svc-demo-a',7,
  'portal-imagine-mock','req-demo-race','nonce-demo-race','$digest');" | tr -d '\r\n')"
if [[ "$result" != accepted_synthetic_only ]]; then
  echo "FAIL: new grant revision did not permit fresh synthetic retry: $result" >&2
  exit 1
fi
echo "PASS 30 rejected race can be retried only after synthetic reactivation"
