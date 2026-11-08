#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
printf 'PHP: '
php -r 'echo PHP_VERSION, PHP_EOL;'
printf '%s\n' 'Static PHP syntax:'
find src app bootstrap routes public tests database -type f -name '*.php' -print0 2>/dev/null | while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
    echo "PASS $file"
done
printf '%s\n' 'Standalone synthetic domain checks:'
php tests/Unit/F102AStandalone.php
if command -v composer >/dev/null 2>&1; then
    echo 'Composer available: validating manifest only; NOT installing dependencies'
    composer validate --no-check-publish
else
    echo 'BLOCKED: Composer not available; framework and PostgreSQL tests NOT executed'
fi
