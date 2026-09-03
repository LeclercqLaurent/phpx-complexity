#!/usr/bin/env bash
# Construit le PHAR distribuable.
#
# Deux contraintes que « box compile » seul ne règle pas :
#   - php.ini livre phar.readonly=On, il faut le lever le temps du build ;
#   - box embarque tout vendor/, donc PHPUnit et PHPStan si les dépendances de
#     dev sont installées. On bascule sur --no-dev le temps de compiler, puis on
#     restaure l'environnement de développement quoi qu'il arrive.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

BOX="var/box/vendor/bin/box"
if [ ! -x "$BOX" ]; then
    echo "box absent : composer require --working-dir=var/box humbug/box:^4.7" >&2
    exit 2
fi

restore() {
    echo "== restauration des dépendances de développement"
    composer install --quiet
}
trap restore EXIT

echo "== dépendances de production seules"
composer install --no-dev --quiet || exit 2

echo "== compilation"
php -d phar.readonly=0 "$BOX" compile --no-parallel || exit 2
