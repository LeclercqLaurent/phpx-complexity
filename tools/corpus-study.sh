#!/usr/bin/env bash
# Récupère un corpus de projets PHP réels et fige un audit --json par projet.
#
# Sert à VALIDER les deux lentilles maison (garde-fous 1 et 2 de la doc de conception) :
# non-redondance vis-à-vis de S3776, et défendabilité des seuils. Les résultats
# sont ensuite agrégés par tools/corpus-report.php.
#
# Les sources sont mises en cache sous var/corpus-src/ : réévaluer une variante
# de lentille ne doit pas coûter un nouveau clonage. Supprimer ce dossier pour
# repartir de dépôts frais. (L'étude initiale a été menée via la sous-commande
# « fetch » de l'outil ; on clone ici directement, le cache étant l'objectif.)
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

OUT="var/corpus"
SRC="var/corpus-src"
mkdir -p "$OUT" "$SRC"

# Le code de test gonflerait le corpus de méthodes non représentatives.
EXCLUDES=(--exclude=/tests/ --exclude=/Tests/ --exclude=/test/ --exclude=/spec/
          --exclude=/fixtures/ --exclude=/Fixtures/ --exclude=/stubs/ --exclude=/Stubs/)

REPOS=(
    "php-parser|https://github.com/nikic/PHP-Parser.git"
    "symfony-console|https://github.com/symfony/console.git"
    "laravel-framework|https://github.com/laravel/framework.git"
    "phpunit|https://github.com/sebastianbergmann/phpunit.git"
    "guzzle|https://github.com/guzzle/guzzle.git"
    "doctrine-orm|https://github.com/doctrine/orm.git"
    "phpstan-src|https://github.com/phpstan/phpstan-src.git"
    "composer|https://github.com/composer/composer.git"
    "flysystem|https://github.com/thephpleague/flysystem.git"
    "monolog|https://github.com/Seldaek/monolog.git"
)

for entry in "${REPOS[@]}"; do
    name="${entry%%|*}"
    url="${entry#*|}"
    printf '== %-20s ' "$name"
    if [ -d "$SRC/$name" ]; then
        printf '(cache) '
    elif ! git clone --depth=1 --quiet "$url" "$SRC/$name" 2> "$OUT/$name.err"; then
        printf 'ÉCHEC clone : %s\n' "$(head -1 "$OUT/$name.err")"
        continue
    fi

    php bin/phpx-complexity "$SRC/$name" --json "${EXCLUDES[@]}" > "$OUT/$name.json"
    printf '%s méthodes\n' "$(php -r '$d=json_decode(file_get_contents($argv[1]),true);echo $d["summary"]["methods"]??0;' "$OUT/$name.json")"
done

printf '\nCorpus dans %s\n' "$OUT"
