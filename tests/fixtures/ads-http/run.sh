#!/usr/bin/env bash
set -euo pipefail
umask 077

fixture_dir="$(mktemp -d /tmp/ads-http.XXXXXX)"
peer_pid=''
cleanup() {
  if [[ -n "$peer_pid" ]]; then
    kill "$peer_pid" 2>/dev/null || true
    wait "$peer_pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT

make_ca() {
  local prefix="$1"
  openssl req -x509 -newkey rsa:2048 -nodes -days 1 \
    -subj "/CN=Synthetic Ads CI $prefix" \
    -addext 'basicConstraints=critical,CA:TRUE' \
    -addext 'keyUsage=critical,keyCertSign,cRLSign' \
    -keyout "$fixture_dir/$prefix.key" -out "$fixture_dir/$prefix.pem" >/dev/null 2>&1
}
make_leaf() {
  local prefix="$1" issuer="$2" names="$3"
  openssl req -new -newkey rsa:2048 -nodes -subj '/CN=Synthetic Ads CI peer' \
    -keyout "$fixture_dir/$prefix.key" -out "$fixture_dir/$prefix.csr" >/dev/null 2>&1
  cat > "$fixture_dir/$prefix.ext" <<EOF
basicConstraints=critical,CA:FALSE
keyUsage=critical,digitalSignature,keyEncipherment
extendedKeyUsage=serverAuth
subjectAltName=$names
EOF
  openssl x509 -req -days 1 -in "$fixture_dir/$prefix.csr" \
    -CA "$fixture_dir/$issuer.pem" -CAkey "$fixture_dir/$issuer.key" \
    -CAcreateserial -extfile "$fixture_dir/$prefix.ext" \
    -out "$fixture_dir/$prefix.pem" >/dev/null 2>&1
}

make_ca ca
make_ca unrelated-ca
make_leaf trusted ca 'DNS:googleads.googleapis.com,DNS:graph.facebook.com'
make_leaf wrong-host ca 'DNS:wrong-host.invalid'
make_leaf untrusted unrelated-ca 'DNS:googleads.googleapis.com,DNS:graph.facebook.com'
printf '%s\n' '{"case_id":"startup","responses":[],"expected_requests":[]}' > "$fixture_dir/control.json"
python3 -B tests/fixtures/ads-http/serve.py "$fixture_dir" > "$fixture_dir/peer.log" 2>&1 &
peer_pid="$!"
for ((attempt=0; attempt<100; attempt++)); do
  [[ -f "$fixture_dir/ready" ]] && break
  kill -0 "$peer_pid" 2>/dev/null || { echo 'Synthetic TLS peer failed to start'; exit 1; }
  sleep 0.05
done
[[ -f "$fixture_dir/ready" ]] || { echo 'Synthetic TLS peer readiness timeout'; exit 1; }

# The production transport accepts no endpoint or TLS override. These settings
# trust only this test CA in the isolated PHP process and poison ambient proxies.
HTTPS_PROXY='http://127.0.0.1:9' HTTP_PROXY='http://127.0.0.1:9' \
https_proxy='http://127.0.0.1:9' http_proxy='http://127.0.0.1:9' \
ALL_PROXY='http://127.0.0.1:9' all_proxy='http://127.0.0.1:9' \
NO_PROXY='' no_proxy='' \
  php -d "curl.cainfo=$fixture_dir/ca.pem" -d zend.exception_ignore_args=0 \
    tests/admin_phase10_ads_http_transport_test.php "$fixture_dir"
