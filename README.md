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
pur, sans PHPStan), dont la **conformité aux règles publiées est testée** cas par
cas (`tests/Lens/*ConformanceTest.php`, valeurs attendues dérivées de la
spécification et non de notre sortie). Écarts assumés et documentés dans
`CognitiveComplexityLens` : la récursion n'est pas comptée, `match` est traité
comme un `switch`, et `else { if ... }` est compté comme un `else if` faute de
pouvoir les distinguer dans l'AST. `live_peak` et `entangle` sont propres à l'outil — leur
non-redondance et leurs seuils sont mesurés sur 40 594 méthodes de 10 projets
publics dans [docs/validation-lentilles.md](docs/validation-lentilles.md),
reproductible via `tools/corpus-study.sh`.

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

## Auditer un dépôt distant (`fetch`)

```bash
bin/phpx-complexity fetch https://github.com/vendor/projet.git
bin/phpx-complexity fetch git@github.com:vendor/projet.git --qa --keep
```

Clone le dépôt en superficiel dans un dossier temporaire, lui applique l'audit
ordinaire, puis **nettoie — y compris si l'analyse échoue**. `--keep` conserve la
copie et affiche son chemin.

> **C'est la seule partie de l'outil qui accède au réseau.** Le cœur d'analyse
> (`ProjectAnalyzer` et les lentilles) ne connaît qu'un **chemin local** : la
> garantie hors-ligne de l'analyse elle-même reste entière. Le réseau est confiné
> à un wrapper explicitement opt-in, qu'aucune option de l'audit ne peut
> déclencher.

**Ce qui est accepté** : `https://`, `ssh://` et la forme `git@hote:chemin`. Sont
refusés `git://` (ni chiffré ni authentifié), `file://` et les chemins locaux (un
dossier local s'analyse directement), le transport `ext::` (exécution de commande
arbitraire) et tout ce qui commence par un tiret, que git prendrait pour une
option. La commande est passée en tableau — aucun shell, donc aucune
interpolation — avec `--` avant l'URL, hooks neutralisés et clone superficiel.

**Dépôts privés** : l'authentification est celle de git (agent SSH, credential
helper). Rien n'est réinventé et **aucune invite n'est posée** : un dépôt
inaccessible échoue immédiatement au lieu de faire attendre. Une clé protégée par
phrase de passe sans agent chargé échouera donc aussi.

**Ce que le clone ne fournit pas** : `--coverage` a besoin d'un rapport de
couverture **déjà généré** par le projet ; un clone seul ne l'apporte pas, le
module ne verra donc que la présence statique de tests. `--qa`, lui, fonctionne
pleinement (il lit des fichiers de configuration présents dans le dépôt).

Nécessite le binaire `git` sur la machine ; son absence est signalée clairement.

## Baseline & deltas (`--baseline`)

Sur un projet existant, `--fail-on-violations` échoue dès le premier run : des
violations héritées que personne ne corrigera d'un coup, donc on désactive le
gate et il ne sert plus à rien. La baseline **accepte l'existant** et ne fait
échouer que ce qui **empire** — le modèle de PHPStan ou Psalm.

C'est aussi la réponse à la limite philosophique de l'outil : l'entropie
essentielle étant irréductible, on n'exige pas zéro complexité, on exige qu'elle
ne régresse pas.

```bash
# 1. Figer l'instantané de référence (une fois, committé dans le dépôt)
bin/phpx-complexity src/ --json > baseline.json

# 2. Comparer l'état courant à la référence
bin/phpx-complexity src/ --baseline=baseline.json

# 3. Gate CI : n'échouer que sur les régressions
bin/phpx-complexity src/ --baseline=baseline.json --fail-on-new
```

Un instantané **est une sortie `--json` figée** : aucun format n'est inventé pour
la baseline. Le rapport distingue quatre faits :

| Catégorie | Sens |
|---|---|
| **Nouvelles violations** | dépasse le seuil maintenant, pas dans la référence (méthode neuve incluse) |
| **Violations aggravées** | déjà au-dessus, valeur en hausse |
| **Violations résolues** | était au-dessus, ne l'est plus |
| **Apparues / disparues** | méthodes ajoutées ou supprimées, pour le contexte |

Une violation héritée **inchangée** n'apparaît nulle part : seul le mouvement est
montré. `--fail-on-new` sort en 1 sur les seules nouvelles et aggravées.

Points de méthode :

- **L'identité d'une méthode est `fichier::méthode`**, jamais la ligne — celle-ci
  se décale au moindre ajout en amont et ferait passer un fichier entier pour
  réécrit. Les homonymes d'un même fichier sont départagés par un rang dans
  l'ordre des lignes. Un renommage apparaît en disparue + apparue (pas de
  détection de rename, comme `git` sans heuristique).
- **Le classement se fait sur les seuils courants**, ceux que le gate doit
  imposer. Si un seuil a bougé depuis l'instantané, le rapport le signale : les
  valeurs brutes, elles, restent comparables.
- **`baseline.json` se committe et se régénère volontairement**, jamais
  automatiquement — sans quoi le cliquet ne retient plus rien.

## Installation

```bash
composer install
```

## Usage

```bash
# Audit lisible d'un projet
bin/phpx-complexity /chemin/vers/projet

# Auditer un dépôt distant (clone superficiel temporaire, puis nettoyage)
bin/phpx-complexity fetch https://github.com/vendor/projet.git

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
    "thresholds": { "cognitive": 15, "params": 7, "returns": 3, "live_peak": 8, "entangle": 3 },
    "exclude": ["/vendor/", "/tests/"],
    "top": 25,
    "html": { "path": "build/phpx-complexity.html" }
}
```

Les fragments d'`exclude` se comparent au chemin **relatif à la racine auditée**,
jamais au chemin absolu : auditer un projet installé dans `/var/www/monprojet`
n'est donc pas vidé par le fragment `/var/`.

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

Le script inclut l'outil **appliqué à son propre code, en mode cliquet** : les
quelques dépassements hérités sont figés dans `baseline.json` et passent, toute
régression échoue.

État actuel : **PHPStan level 9 sans erreur**, PSR-12 respecté, tests verts,
trois dépassements hérités figés.

## Build PHAR

```bash
composer global require humbug/box
box compile
```

produit `phpx-complexity.phar`, binaire unique distribuable.

## Origine

Les lentilles `params` (S107) et `returns` (S1142) dérivent de règles PHPStan
custom Codeam, réimplémentées ici sans couplage PHPStan. Sous licence MIT.
