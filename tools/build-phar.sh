#!/usr/bin/env bash
# Construit le PHAR distribuable.
#
# Two constraints that "box compile" alone does not settle:
#   - php.ini livre phar.readonly=On, il faut le lever le temps du build ;
#   - box bundles the whole of vendor/, hence PHPUnit and PHPStan when the dev
#     dependencies are installed. We switch to --no-dev for the compilation, then
#     restore the development environment whatever happens.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

BOX="var/box/vendor/bin/box"
if [ ! -x "$BOX" ]; then
    echo "box absent : composer require --working-dir=var/box humbug/box:^4.7" >&2
    exit 2
fi

restore() {
    echo "== restoring the development dependencies"
    composer install --quiet
}
trap restore EXIT

echo "== production dependencies only"
composer install --no-dev --quiet || exit 2

echo "== compilation"
php -d phar.readonly=0 "$BOX" compile --no-parallel || exit 2
