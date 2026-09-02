# phpx-complexity

Auditeur de complexité PHP **multi-lentilles**, hors-ligne, à dépendance unique
(`nikic/php-parser`). Il reproduit nativement la famille de règles SonarQube
« complexité » et ajoute une lentille d'**intrication** des données, puis confronte
toutes les lentilles dans un **rapport de divergence**.

## Pourquoi

Une métrique de complexité isolée a des angles morts. La complexité cognitive
(S3776) compte les branches mais ignore une méthode sans `if` qui entremêle dix
variables ; le nombre de paramètres (S107) ignore la logique interne. En croisant
plusieurs lentilles, on repère les méthodes où elles **se contredisent** — c'est
là que se cachent les vrais problèmes qu'un linter mono-métrique laisse passer.

Sur un code propre, les lentilles convergent. Leur **divergence** est le signal.

## Les 5 lentilles

| Clé | Référence | Mesure |
|---|---|---|
| `cognitive` | S3776 | Complexité cognitive (branches + imbrication + opérateurs logiques) |
| `params` | S107 | Nombre de paramètres |
| `returns` | S1142 | Nombre d'instructions `return` |
| `live_peak` | — | Pic de variables vivantes simultanément (mémoire de travail) |
| `entangle` | — | Intrication : degré moyen du graphe de co-occurrence des variables |

`cognitive`, `params` et `returns` sont des réimplémentations natives (php-parser
pur, sans PHPStan). `live_peak` et `entangle` sont propres à l'outil.

## Présence des outils de QA (`--qa`)

Au-delà de la complexité, `--qa` audite l'**outillage qualité** du projet cible :
analyse statique (PHPStan, Psalm), standards (PHP-CS-Fixer, PHP_CodeSniffer),
refactoring (Rector), tests (PHPUnit, Pest, Behat, Infection), CI (GitHub Actions,
GitLab CI), EditorConfig. Chaque outil est détecté via `composer.json`
(require / require-dev) **ou** la présence de son fichier de config. La racine du
projet est résolue en remontant jusqu'au `composer.json` (auditer `app/src` trouve
la config QA dans `app/`).

Les outils déclarés `qa.required` dans la config qui manquent font échouer le
mode gate (`--fail-on-violations`).

## Tests & couverture (`--coverage`)

La couverture est une mesure d'**exécution** : l'outil étant statique et hors-ligne,
il ne la calcule pas. `--coverage` produit donc deux blocs honnêtes et distincts :

1. **Couverture réelle** — lecture d'un rapport déjà généré par le projet
   (Clover de PHPUnit ou Cobertura), auto-détecté aux emplacements usuels ou via
   `coverage.path`. Absence de rapport ⇒ **« non mesurée »** (jamais « 0 % », qui
   serait un verdict infondé).
2. **Présence de tests** (proxy statique) — nombre de classes/méthodes de test et
   liste des classes source **sans aucune** classe `*Test`. C'est un *plancher*,
   **pas** de la couverture : qu'une classe `FooTest` existe ne prouve pas que
   `Foo` est testée utilement. Répond à « des tests sont-ils *implémentés* ? »,
   pas à « couvrent-ils *suffisamment* ? ».

```bash
bin/phpx-complexity /chemin/vers/projet --coverage
```

## Installation

```bash
composer install
```

## Usage

```bash
# Audit lisible d'un projet
bin/phpx-complexity /chemin/vers/projet

# Sortie JSON pour la CI
bin/phpx-complexity /chemin/vers/projet --json > complexity.json

# Rapport HTML autonome (un seul fichier, hors-ligne, ouvrable au navigateur)
bin/phpx-complexity /chemin/vers/projet --html=rapport.html
# … le nuage de points lentille-vs-lentille rend visible la divergence

# Vérifier aussi la présence des outils de QA (PHPStan, CS-Fixer, PHPUnit, CI…)
bin/phpx-complexity /chemin/vers/projet --qa

# Mode gate : code de sortie 1 si un seuil est dépassé
bin/phpx-complexity /chemin/vers/projet --fail-on-violations

# Aide
bin/phpx-complexity --help
```

## Configuration

Place un `phpx-complexity.json` à la racine du projet audité (voir
`phpx-complexity.dist.json`) :

```json
{
    "thresholds": { "cognitive": 15, "params": 7, "returns": 3, "live_peak": 8, "entangle": 4 },
    "exclude": ["/vendor/", "/tests/"],
    "top": 25,
    "html": { "path": "build/phpx-complexity.html" }
}
```

`html.path` fixe le fichier de sortie du rapport HTML : avec `--html` (sans
valeur), le rapport est écrit à ce chemin au lieu de la sortie standard.
Précédence : `--html=FICHIER` (CLI) > `html.path` (config) > stdout.

## Qualité du projet

Le projet s'applique à lui-même les exigences qu'il audite :

```bash
scripts/qa.sh    # PHP-CS-Fixer (PSR-12) + PHPStan level 9 + PHPUnit
composer qa      # idem
```

Garde-fou local à lancer avant chaque commit : un code de sortie non nul signale
un commit à corriger. Comme le reste de l'outil, tout est hors-ligne.

État actuel : **PHPStan level 9 sans erreur**, PSR-12 respecté, tests verts.
Quelques méthodes dépassent encore les seuils de complexité de l'outil lui-même
(dont `Cli\Application::run`) : dette assumée et visible, que la future baseline
(`--baseline`) figera pour n'échouer que sur les régressions.

## Build PHAR

```bash
composer global require humbug/box
box compile
```

produit `phpx-complexity.phar`, binaire unique distribuable.

## Origine

Les lentilles `params` (S107) et `returns` (S1142) dérivent de règles PHPStan
custom Codeam, réimplémentées ici sans couplage PHPStan. Sous licence MIT.
