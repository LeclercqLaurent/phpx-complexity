#!/usr/bin/env bash
# Fetches a corpus of real PHP projects and freezes one --json audit per project.
#
# It serves to VALIDATE the two in-house lenses (guards 1 and 2 of the design
# notes): non-redundancy against S3776, and defensible thresholds. The results are
# then aggregated by tools/corpus-report.php.
#
# Sources are cached under var/corpus-src/: re-evaluating a lens variant must not
# cost a fresh clone. Delete that directory to start from fresh repositories. (The
# initial study was run through the tool's own "fetch" subcommand; here we clone
# directly, since caching is the point.)
set -uo pipefail

cd "$(dirname "$0")/.." || exit 2

OUT="var/corpus"
SRC="var/corpus-src"
mkdir -p "$OUT" "$SRC"

# Test code would inflate the corpus with unrepresentative methods.
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
    printf '%s methods\n' "$(php -r '$d=json_decode(file_get_contents($argv[1]),true);echo $d["summary"]["methods"]??0;' "$OUT/$name.json")"
done

printf '\nCorpus dans %s\n' "$OUT"
