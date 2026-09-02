#!/usr/bin/env bash
# Garde-fou qualité local, hors-ligne. À lancer avant chaque commit.
# Code de sortie non nul = commit à corriger.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

if [ ! -d vendor ]; then
    echo "vendor/ absent : lancer d'abord « composer install »." >&2
    exit 2
fi

status=0

run() {
    local label="$1"
    shift
    printf '\n\033[1m== %s\033[0m\n' "$label"
    if "$@"; then
        printf '\033[32m   OK\033[0m\n'
    else
        printf '\033[31m   ÉCHEC\033[0m\n'
        status=1
    fi
}

run "PHP-CS-Fixer (PSR-12)" php vendor/bin/php-cs-fixer fix --dry-run --diff
run "PHPStan (level 9)" php vendor/bin/phpstan analyse --no-progress
run "PHPUnit" php vendor/bin/phpunit

printf '\n'
[ "$status" -eq 0 ] && printf '\033[32mQA OK\033[0m\n' || printf '\033[31mQA en échec\033[0m\n'
exit $status
