#!/usr/bin/env bash
set -euo pipefail
test "$APP_ENV" = testing
test "$PERF_F63_INTEGRITY_LAB" = 1
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
build="contracts/demo/f63-build-integrity-showcase.php"
verify="contracts/demo/f63-verify-integrity-showcase.php"
root="$tmp/perf-showcase-original"
php "$build" "$root"
php "$verify" "$root"
echo "PASS F63 initial package verified"
second="$tmp/perf-showcase-second"
php "$build" "$second"
diff -qr "$root" "$second"
echo "PASS F63 deterministic double build"
if php "$build" "$root" >/dev/null 2>&1; then
  echo "FAIL F63 overwrote original"; exit 1
fi
echo "PASS F63 no overwrite"
if APP_ENV=production php "$verify" "$root" >/dev/null 2>&1; then
  echo "FAIL F63 production verifier admitted test mode"; exit 1
fi
if PERF_F63_INTEGRITY_LAB=0 php "$verify" "$root" >/dev/null 2>&1; then
  echo "FAIL F63 missing flag admitted"; exit 1
fi
if APP_ENV=production php "$build" "$tmp/perf-showcase-prod" >/dev/null 2>&1; then
  echo "FAIL F63 production builder admitted"; exit 1
fi
echo "PASS F63 negative feature flags"
bad(){
  local name="$1"
  if php "$verify" "$tmp/$name" >/dev/null 2>&1; then
    echo "FAIL F63 verifier accepted tampered bundle $name";exit 1
  fi
  echo "PASS F63 refused $name"
}
clone(){
  cp -a "$root" "$tmp/$1"
}
clone perf-showcase-extra
printf 'unrelated file' > "$tmp/perf-showcase-extra/unexpected.txt"
bad perf-showcase-extra

clone perf-showcase-missing
rm "$tmp/perf-showcase-missing/revisoes.html"
bad perf-showcase-missing

clone perf-showcase-script-changed
printf '\n<script>alert(1)</script>\n' >> "$tmp/perf-showcase-script-changed/index.html"
bad perf-showcase-script-changed

clone perf-showcase-unsafe-symlink
rm "$tmp/perf-showcase-unsafe-symlink/revisoes.html"
ln -s /etc/hosts "$tmp/perf-showcase-unsafe-symlink/revisoes.html"
bad perf-showcase-unsafe-symlink

clone perf-showcase-unexpected-directory
mkdir "$tmp/perf-showcase-unexpected-directory/extra"
bad perf-showcase-unexpected-directory

clone perf-showcase-missing-manifest
rm "$tmp/perf-showcase-missing-manifest/integrity.json"
bad perf-showcase-missing-manifest

clone perf-showcase-broken-checksum
sed -i 's/"aggregate_sha256": "/ "aggregate_sha256": "a/' "$tmp/perf-showcase-broken-checksum/integrity.json"
bad perf-showcase-broken-checksum

clone perf-showcase-wrong-billing
sed -i 's/"billing_enabled": false/"billing_enabled": true/' "$tmp/perf-showcase-wrong-billing/manifest.json"
bad perf-showcase-wrong-billing

clone perf-showcase-recomputed-billing
python3 - "$tmp/perf-showcase-recomputed-billing" <<'PY'
import sys,json,hashlib,pathlib
root=pathlib.Path(sys.argv[1])
man=root/"manifest.json"
doc=json.loads(man.read_text())
doc["billing_enabled"]=True
man.write_text(json.dumps(doc,ensure_ascii=False,indent=4)+"\n")
integ=root/"integrity.json"
i=json.loads(integ.read_text())
row=i["files"]["manifest.json"]
data=man.read_bytes()
row["size_bytes"]=len(data)
row["sha256"]=hashlib.sha256(data).hexdigest()
lines="".join(f"{name}\t{r['size_bytes']}\t{r['sha256']}\n" for name,r in i["files"].items())
i["aggregate_sha256"]=hashlib.sha256(lines.encode()).hexdigest()
integ.write_text(json.dumps(i,ensure_ascii=False,indent=4)+"\n")
PY
bad perf-showcase-recomputed-billing

clone perf-showcase-recomputed-network
python3 - "$tmp/perf-showcase-recomputed-network" <<'PY'
import sys,json,hashlib,pathlib
root=pathlib.Path(sys.argv[1])
html=root/"index.html"
html.write_text(html.read_text()+"\n<script src='https://evil.example/app.js'></script>\n")
integ=root/"integrity.json"
i=json.loads(integ.read_text())
row=i["files"]["index.html"]
data=html.read_bytes()
row["size_bytes"]=len(data)
row["sha256"]=hashlib.sha256(data).hexdigest()
lines="".join(f"{name}\t{r['size_bytes']}\t{r['sha256']}\n" for name,r in i["files"].items())
i["aggregate_sha256"]=hashlib.sha256(lines.encode()).hexdigest()
integ.write_text(json.dumps(i,ensure_ascii=False,indent=4)+"\n")
PY
bad perf-showcase-recomputed-network
echo "PASS F63 all negative integrity checks, offline NO-GO preserved"
