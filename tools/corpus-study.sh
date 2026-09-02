#!/usr/bin/env bash
# Récupère un corpus de projets PHP réels et fige un audit --json par projet.
#
# Sert à VALIDER les deux lentilles maison (garde-fous 1 et 2 de la doc de conception) :
# non-redondance vis-à-vis de S3776, et défendabilité des seuils. Les résultats
# sont ensuite agrégés par tools/corpus-report.php.
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

OUT="var/corpus"
export TMPDIR="$PWD/var/corpus-tmp"
mkdir -p "$OUT" "$TMPDIR"

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
    printf '== %-20s %s\n' "$name" "$url"
    if php bin/phpx-complexity fetch "$url" --json "${EXCLUDES[@]}" > "$OUT/$name.json" 2> "$OUT/$name.err"; then
        printf '   %s méthodes\n' "$(php -r '$d=json_decode(file_get_contents($argv[1]),true);echo $d["summary"]["methods"]??0;' "$OUT/$name.json")"
    else
        printf '   ÉCHEC : %s\n' "$(head -2 "$OUT/$name.err")"
        rm -f "$OUT/$name.json"
    fi
done

printf '\nCorpus dans %s\n' "$OUT"
