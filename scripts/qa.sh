#!/usr/bin/env bash
# Local, offline quality gate. Run it before every commit.
# A non-zero exit status means the commit needs fixing.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

if [ ! -d vendor ]; then
    echo "vendor/ is missing, run 'composer install' first." >&2
    exit 2
fi

COVERAGE_MIN=90
status=0

run() {
    local label="$1"
    shift
    printf '\n\033[1m== %s\033[0m\n' "$label"
    if "$@"; then
        printf '\033[32m   OK\033[0m\n'
    else
        printf '\033[31m   FAILED\033[0m\n'
        status=1
    fi
}

run "PHP-CS-Fixer (PSR-12)" php vendor/bin/php-cs-fixer fix --dry-run --diff
run "PHPStan (level 9)" php vendor/bin/phpstan analyse --no-progress
# Coverage can only be measured with Xdebug or PCOV. With no driver we run the
# tests without it rather than fail: a partial guard beats a bypassed one.
if php -r 'exit((extension_loaded("xdebug") || extension_loaded("pcov")) ? 0 : 1);'; then
    run "PHPUnit + coverage (floor ${COVERAGE_MIN}%)" bash -c \
        "XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-clover var/clover.xml \
         && php tools/coverage-gate.php var/clover.xml ${COVERAGE_MIN}"
else
    run "PHPUnit (coverage not measured, no Xdebug or PCOV)" php vendor/bin/phpunit
fi
# Dogfooding: the tool audits its own code in ratchet mode. The inherited
# violations sit in baseline.json and pass; any regression fails.
run "phpx-complexity (ratchet)" php bin/phpx-complexity src/ --baseline=baseline.json --fail-on-new

printf '\n'
[ "$status" -eq 0 ] && printf '\033[32mQA OK\033[0m\n' || printf '\033[31mQA FAILED\033[0m\n'
exit $status
