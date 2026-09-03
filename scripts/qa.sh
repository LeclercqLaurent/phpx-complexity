#!/usr/bin/env bash
# Garde-fou qualité local, hors-ligne. À lancer avant chaque commit.
# Code de sortie non nul = commit à corriger.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

if [ ! -d vendor ]; then
    echo "vendor/ absent : lancer d'abord « composer install »." >&2
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
        printf '\033[31m   ÉCHEC\033[0m\n'
        status=1
    fi
}

run "PHP-CS-Fixer (PSR-12)" php vendor/bin/php-cs-fixer fix --dry-run --diff
run "PHPStan (level 9)" php vendor/bin/phpstan analyse --no-progress
# La couverture n'est mesurable qu'avec Xdebug ou PCOV. Sans pilote, on lance les
# tests sans elle plutôt que d'échouer : mieux vaut un garde-fou partiel qu'un
# garde-fou contourné.
if php -r 'exit((extension_loaded("xdebug") || extension_loaded("pcov")) ? 0 : 1);'; then
    run "PHPUnit + couverture (plancher ${COVERAGE_MIN} %)" bash -c \
        "XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-clover var/clover.xml \
         && php tools/coverage-gate.php var/clover.xml ${COVERAGE_MIN}"
else
    run "PHPUnit (couverture non mesurée : ni Xdebug ni PCOV)" php vendor/bin/phpunit
fi
# Dogfooding : l'outil s'audite lui-même en mode cliquet. Les trois dépassements
# hérités figurent dans baseline.json et passent ; toute régression échoue.
run "phpx-complexity (cliquet)" php bin/phpx-complexity src/ --baseline=baseline.json --fail-on-new

printf '\n'
[ "$status" -eq 0 ] && printf '\033[32mQA OK\033[0m\n' || printf '\033[31mQA en échec\033[0m\n'
exit $status
