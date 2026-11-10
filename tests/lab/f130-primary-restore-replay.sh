#!/usr/bin/env bash
set -euo pipefail
test "${APP_ENV:-}" = testing
test "${PERF_F130_LAB:-}" = 1
test "${PGDATABASE:-}" = perf_f130_ci
test "${WITNESS_DB:-}" = perf_f130_witness_ci
test -n "${RUNNER_TEMP:-}"
: "${PGHOST:=127.0.0.1}"
: "${PGPASSWORD:?CI-only primary password required}"
: "${WITNESS_PASSWORD:?CI-only witness password required}"
: "${PERF_F129_WRITER_PASSWORD:?CI-only writer password required}"
: "${PERF_F129_SIM_PASSWORD:?CI-only simulator password required}"
: "${PERF_F130_WRITER_PASSWORD:?CI-only witness-writer password required}"

p_admin() { PGUSER=postgres PGPORT=5432 PGPASSWORD="$PGPASSWORD" psql -X -A -t -v ON_ERROR_STOP=1 -d perf_f130_ci -c "$1"; }
p_writer() { PGUSER=perf_f129_writer PGPORT=5432 PGPASSWORD="$PERF_F129_WRITER_PASSWORD" psql -X -A -t -v ON_ERROR_STOP=1 -d perf_f130_ci -c "$1"; }
p_sim() { PGUSER=perf_f129_failure_simulator PGPORT=5432 PGPASSWORD="$PERF_F129_SIM_PASSWORD" psql -X -A -t -v ON_ERROR_STOP=1 -d perf_f130_ci -c "$1"; }
w_admin() { PGUSER=postgres PGPORT=5433 PGPASSWORD="$WITNESS_PASSWORD" psql -X -A -t -v ON_ERROR_STOP=1 -d perf_f130_witness_ci -c "$1"; }
w_writer() { PGUSER=perf_f130_writer PGPORT=5433 PGPASSWORD="$PERF_F130_WRITER_PASSWORD" psql -X -A -t -v ON_ERROR_STOP=1 -d perf_f130_witness_ci -c "$1"; }
n=0
check() { n=$((n+1)); if [[ "$2" != "$1" ]]; then echo "FAIL F130: $3 (expected $1; got $2)" >&2; exit 1; fi; }

f1=$(printf 'f130-early-receipt' | sha256sum | cut -d' ' -f1)
f2=$(printf 'f130-lost-quarantine' | sha256sum | cut -d' ' -f1)
check t "$(p_writer "SELECT perf_f129_lab.record_intent('operation_5001','tenant_demo_A','$f1',8)")" "first intent accepted"
check t "$(w_writer "SELECT perf_f130_witness.record_fingerprint('$f1')")" "first witness receipt accepted"

backup="$RUNNER_TEMP/f130-preincident-journal.sql"
PGUSER=postgres PGPORT=5432 PGPASSWORD="$PGPASSWORD" pg_dump -h "$PGHOST" \
  -d perf_f130_ci --data-only --column-inserts --no-owner --no-privileges \
  --table=perf_f129_lab.circuit --table=perf_f129_lab.receipts \
  --table=perf_f129_lab.uncertainty --file="$backup"
test -s "$backup"
check t "$(p_writer "SELECT perf_f129_lab.record_intent('operation_5002','tenant_demo_B','$f2',8)")" "later operation stored"
check t "$(w_writer "SELECT perf_f130_witness.record_fingerprint('$f2')")" "independent receipt stored"
check f "$(p_sim "SELECT perf_f129_lab.mark_uncertain('operation_5002','$f2','timeout')")" "timeout never authorizes"
check t "$(p_admin "SELECT quarantined FROM perf_f129_lab.circuit WHERE singleton=1")" "quarantine in primary before rollback"
check 2 "$(p_admin "SELECT count(*) FROM perf_f129_lab.receipts")" "two receipts before rollback"
check 1 "$(p_admin "SELECT count(*) FROM perf_f129_lab.uncertainty")" "incident before rollback"
check f "$(w_writer "SELECT perf_f130_witness.record_fingerprint('$f2')")" "witness duplicate rejected before rollback"

# Deliberate destructive restore as CI admin. Mirrors a primary-only old backup restore;
# NOT a safe recovery procedure. Never run against production.
p_admin "TRUNCATE perf_f129_lab.uncertainty,perf_f129_lab.receipts,perf_f129_lab.circuit" >/dev/null
PGUSER=postgres PGPORT=5432 PGPASSWORD="$PGPASSWORD" psql -X -v ON_ERROR_STOP=1 \
  -d perf_f130_ci -f "$backup" > /dev/null

check f "$(p_admin "SELECT quarantined FROM perf_f129_lab.circuit WHERE singleton=1")" "old snapshot lost quarantine (EXPECTED VULNERABILITY)"
check 1 "$(p_admin "SELECT count(*) FROM perf_f129_lab.receipts")" "old snapshot forgot newer receipt"
check 0 "$(p_admin "SELECT count(*) FROM perf_f129_lab.uncertainty")" "old snapshot forgot incident"
check t "$(p_writer "SELECT perf_f129_lab.record_intent('operation_5002','tenant_demo_B','$f2',8)")" "primary alone re-accepts forgotten intent (EXPECTED VULNERABILITY)"
check f "$(w_writer "SELECT perf_f130_witness.record_fingerprint('$f2')")" "unrestored witness independently rejects same fingerprint"
check 2 "$(w_admin "SELECT count(*) FROM perf_f130_witness.seen_fingerprints")" "external witness ledger unaffected"
echo "F130 PostgreSQL restore expected-negative: $n assertions PASS; PRIMARY ROLLBACK VULNERABILITY REPRODUCED. NO-GO."
