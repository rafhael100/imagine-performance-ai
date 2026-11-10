#!/usr/bin/env bash
set -euo pipefail
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
bash ops/homologacao/preflight-host-readonly.sh > "$tmp"
jq -e '
  .schema_version == "perf.host.preflight.readonly.v1" and
  .decision == "REQUIRES_HUMAN_REVIEW_NO_GO" and
  .read_only == true and
  .host_mutated == false and
  .secrets_inspected == false and
  .network_requests_made == false and
  .deployment_allowed == false and
  .external_ads_allowed == false and
  .billing_allowed == false and
  (.cpu_count | type == "number") and
  (.ram_total_kb | type == "number") and
  (.root_fs_total_kb | type == "number") and
  (.root_fs_available_kb | type == "number") and
  (.docker_cli_available | type == "boolean") and
  (.docker_compose_available | type == "boolean")
' "$tmp"
grep -q 'REQUIRES_HUMAN_REVIEW_NO_GO' "$tmp"
# Explicitly reject dangerous side effects from the Bash source.
if grep -nE '^\s*(sudo|apt|apt-get|dnf|yum|systemctl|docker run|docker compose up|docker compose down|ufw|iptables|firewall-cmd|curl|wget|ssh|scp|rsync|chmod|chown|rm -r|mv |cp )\b' ops/homologacao/preflight-host-readonly.sh; then
  echo "FAIL: host preflight must not alter services or retrieve credentials" >&2
  exit 1
fi
echo "PASS: read-only VPS host preflight report schema and no-go invariant"
