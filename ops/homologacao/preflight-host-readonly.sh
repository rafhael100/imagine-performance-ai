#!/usr/bin/env bash
# Imagine Performance AI — read-only Ubuntu VPS readiness preflight.
# NEVER installs, restarts, opens ports, changes firewall or reads secrets.
set -euo pipefail

safe_uint() {
  local v="${1:-0}"
  if [[ "$v" =~ ^[0-9]+$ ]]; then printf '%s' "$v"; else printf '0'; fi
}
bool() { if "$@" >/dev/null 2>&1; then printf 'true'; else printf 'false'; fi; }
os_id="$(sed -n 's/^ID=//p' /etc/os-release 2>/dev/null | head -n1 | tr -d '\"' || true)"
os_version="$(sed -n 's/^VERSION_ID=//p' /etc/os-release 2>/dev/null | head -n1 | tr -d '\"' || true)"
if [[ ! "$os_id" =~ ^[a-z0-9_-]+$ ]]; then os_id="unknown"; fi
if [[ ! "$os_version" =~ ^[0-9]+\.[0-9]+$ ]]; then os_version="unknown"; fi
cpu="$(safe_uint "$(getconf _NPROCESSORS_ONLN 2>/dev/null || printf 0)")"
memory_kb="$(safe_uint "$(awk '/^MemTotal:/{print $2; exit}' /proc/meminfo 2>/dev/null || printf 0)")"
disk_available_kb="$(safe_uint "$(df -Pk / 2>/dev/null | awk 'NR==2{print $4}' || printf 0)")"
disk_total_kb="$(safe_uint "$(df -Pk / 2>/dev/null | awk 'NR==2{print $2}' || printf 0)")"
docker_cli="$(bool command -v docker)"
docker_compose='false'
docker_socket='false'
if [[ "$docker_cli" == true ]]; then
  docker_compose="$(bool docker compose version)"
  docker_socket="$(bool docker info)"
fi
os_matches='false'
if [[ "$os_id" == ubuntu && "$os_version" == 24.04 ]]; then os_matches='true'; fi
cpu_sufficient='false'
if (( cpu >= 8 )); then cpu_sufficient='true'; fi
memory_sufficient='false'
if (( memory_kb >= 20971520 )); then memory_sufficient='true'; fi
# Disk size is diagnostic, not a provisioning approval.
printf '{\n'
printf '  "schema_version": "perf.host.preflight.readonly.v1",\n'
printf '  "decision": "REQUIRES_HUMAN_REVIEW_NO_GO",\n'
printf '  "read_only": true,\n'
printf '  "host_mutated": false,\n'
printf '  "secrets_inspected": false,\n'
printf '  "network_requests_made": false,\n'
printf '  "expected_os": "ubuntu-24.04",\n'
printf '  "os_id": "%s",\n' "$os_id"
printf '  "os_version": "%s",\n' "$os_version"
printf '  "os_matches": %s,\n' "$os_matches"
printf '  "cpu_count": %s,\n' "$cpu"
printf '  "cpu_target_met": %s,\n' "$cpu_sufficient"
printf '  "ram_total_kb": %s,\n' "$memory_kb"
printf '  "ram_target_met": %s,\n' "$memory_sufficient"
printf '  "root_fs_total_kb": %s,\n' "$disk_total_kb"
printf '  "root_fs_available_kb": %s,\n' "$disk_available_kb"
printf '  "docker_cli_available": %s,\n' "$docker_cli"
printf '  "docker_compose_available": %s,\n' "$docker_compose"
printf '  "docker_daemon_accessible": %s,\n' "$docker_socket"
printf '  "deployment_allowed": false,\n'
printf '  "external_ads_allowed": false,\n'
printf '  "billing_allowed": false\n'
printf '}\n'
