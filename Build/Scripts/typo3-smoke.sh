#!/usr/bin/env bash
# Smoke install of EXT:rte_ckeditor_pack into a fresh TYPO3 (sqlite), used by ckeditor-rebuild.yml.
#   Build/Scripts/typo3-smoke.sh <13.4|14.3> [extension dir] [work dir]
# Steps: composer project + extension as path repository, `typo3 setup` (sqlite, non-interactive),
# extension:setup, cache:flush, then load the backend login page and the backend import map
# through PHP's built-in server; fails on HTTP >= 500, a PHP fatal or TYPO3's error page.
set -euo pipefail
TYPO3_BRANCH=${1:?usage: typo3-smoke.sh <13.4|14.3> [ext dir] [work dir]}
EXT_DIR=$(cd "${2:-.}" && pwd)
WORK=${3:-$(mktemp -d)/typo3-$TYPO3_BRANCH}
PORT=${SMOKE_PORT:-8080}

composer create-project --no-interaction --no-progress "typo3/cms-base-distribution:^$TYPO3_BRANCH" "$WORK"
cd "$WORK"
composer config repositories.ext path "$EXT_DIR"
composer config minimum-stability dev
composer config prefer-stable true
composer require --no-interaction --no-progress "t3planet/rte-ckeditor-pack:@dev" "typo3/cms-rte-ckeditor:^$TYPO3_BRANCH"

TYPO3_SETUP_ADMIN_PASSWORD='Smoke-Test-Passw0rd!' vendor/bin/typo3 setup --no-interaction --force \
  --driver=sqlite --server-type=other --admin-username=admin --admin-email=smoke@example.com \
  --project-name=smoke --create-site="http://127.0.0.1:$PORT/"
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush

php -S "127.0.0.1:$PORT" -t public > "$WORK/php-server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 2

fail=0
check() {
  local url=$1 out code
  out=$(mktemp)
  code=$(curl -sS -o "$out" -w '%{http_code}' "$url" || echo 000)
  echo "$url -> HTTP $code"
  if [ "$code" -ge 500 ] || [ "$code" = 000 ] || grep -qiE 'Fatal error|getPackageKey\(\)|Oops, an error occurred|Uncaught' "$out"; then
    echo "::error::$url failed (HTTP $code)"; head -c 3000 "$out"; echo; fail=1
  fi
}
check "http://127.0.0.1:$PORT/typo3/"

# Build the backend import map like the backend does (loads every package's JavaScriptModules.php).
cat > "$WORK/importmap-check.php" <<'PHP'
<?php
$classLoader = require __DIR__ . '/vendor/autoload.php';
\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(0, \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_BE);
$container = \TYPO3\CMS\Core\Core\Bootstrap::init($classLoader);
$importMap = $container->get(\TYPO3\CMS\Core\Page\ImportMapFactory::class)->create();
// resolving a module forces every active package's JavaScriptModules.php to be loaded
foreach (['@ckeditor/ckeditor5-core', '@t3planet/RteCkeditorPack/script.js', '@typo3/rte-ckeditor/ckeditor5.js'] as $specifier) {
    $url = $importMap->resolveImport($specifier);
    if (!is_string($url) || $url === '') {
        fwrite(STDERR, "backend import map does not resolve $specifier\n");
        exit(1);
    }
    echo "backend import map OK: $specifier -> $url\n";
}
PHP
php "$WORK/importmap-check.php" || fail=1

if grep -qiE 'PHP Fatal|getPackageKey' "$WORK/php-server.log" var/log/*.log 2>/dev/null; then
  echo "::error::PHP fatal in server/TYPO3 log"; grep -iE 'PHP Fatal|getPackageKey' "$WORK/php-server.log" var/log/*.log || true; fail=1
fi
[ "$fail" = 0 ] && echo "TYPO3 $TYPO3_BRANCH smoke install OK"
exit $fail
