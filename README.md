# phpx-complexity

Auditeur de complexité PHP **multi-lentilles**, **hors-ligne**, à dépendance
unique (`nikic/php-parser`). Il reproduit nativement la famille de règles
SonarQube « complexité », y ajoute deux lentilles propres, puis **confronte les
lentilles entre elles** pour révéler ce qu'une métrique isolée laisse passer.

PHP ≥ 8.2 · MIT · `codeam/phpx-complexity`

---

## Pourquoi

Une métrique de complexité isolée a des angles morts. La complexité cognitive
(S3776) compte les branches mais ignore une méthode sans `if` qui entremêle dix
variables ; le nombre de paramètres (S107) ignore la logique interne.

En croisant plusieurs lentilles, on repère les méthodes où elles **se
contredisent** — c'est là que se cachent les problèmes qu'un linter mono-métrique
laisse passer. Sur un code propre, les lentilles convergent. **Leur divergence est
le signal.**

Ce n'est pas une intuition : mesuré sur 40 594 méthodes de 10 projets PHP
publics, les corrélations de rangs entre lentilles vont de −0,02 à 0,72. Elles
classent donc réellement différemment. Détail dans
[docs/validation-lentilles.md](docs/validation-lentilles.md).

### Principe directeur : factuel, jamais de score

**L'outil ne produit aucun score, ni par item ni global.** Un audit se veut
factuel ; un score est subjectif et invite à l'optimiser au lieu de comprendre.
On affiche des **compteurs et des valeurs brutes** confrontés à des seuils.

---

## Les 5 lentilles

| Clé | Réf. | Mesure | Seuil |
|---|---|---|---:|
| `cognitive` | S3776 | Complexité cognitive : branches + imbrication + séquences d'opérateurs logiques | 15 |
| `params` | S107 | Nombre de paramètres de la signature | 7 |
| `returns` | S1142 | Nombre d'instructions `return` | 3 |
| `live_peak` | — | Pic de variables vivantes simultanément (mémoire de travail, 7±2) | 8 |
| `entangle` | — | Intrication : degré moyen du graphe de co-occurrence des variables | 3 |

**`cognitive`, `params` et `returns` sont des réimplémentations natives**
(php-parser pur, sans PHPStan). Leur conformité aux règles publiées est **testée
cas par cas**, avec des valeurs attendues dérivées de la spécification et non de
notre sortie — seule façon pour un test de détecter une réimplémentation fautive
(`tests/Lens/*ConformanceTest.php`).

Écarts assumés et documentés : la récursion n'est pas comptée (elle demanderait
une analyse inter-procédurale), `match` est traité comme un `switch` (postérieur à
la spec), et `else { if ... }` est compté comme un `else if` — php-parser produit
le même arbre pour les deux, et PHP lui-même ne distingue pas `else if` de
`elseif`.

**`live_peak` et `entangle` sont propres à l'outil.** Elles se lisent **en regard
l'une de l'autre** : un pic élevé sans intrication (une factory à 8 champs) n'est
pas un problème ; un pic élevé *avec* intrication forte l'est.

Réserve mesurée et assumée : `live_peak` compte les paramètres **utilisés** dans
le corps — pour le lecteur, un argument qu'il faut garder en tête occupe bien la
mémoire de travail. Elle n'est donc pas orthogonale à S107 (corrélation 0,70,
supérieure à celle avec S3776 à 0,64).

### Le rapport de divergence

Le cœur de l'outil. Il confronte les **rangs centiles** des méthodes selon chaque
lentille et met en avant celles où les lentilles se contredisent — basse en S3776
mais haute en vivacité, par exemple. Masquable avec `--no-divergence`.

---

## Installation

Depuis un clone du dépôt :

```bash
composer install
bin/phpx-complexity --help
```

Le PHAR (voir *Build PHAR*) est un binaire unique, sans dépendance à installer :
c'est la forme la plus commode pour auditer des projets tiers.

> Le paquet **n'est pas publié sur Packagist** à ce jour : `composer require
> codeam/phpx-complexity` ne fonctionnera pas encore. Le nom est celui déclaré
> dans `composer.json` pour le jour où il le sera.

---

## Commandes

### Forme générale

```
phpx-complexity [audit] [CHEMIN] [options]
phpx-complexity fetch URL [options]
```

Le verbe est optionnel : `audit` est implicite, `fetch` est le seul explicite.
Sans chemin, le répertoire courant est audité.

> Un dossier nommé littéralement `fetch` ou `audit` serait pris pour un verbe :
> écrire `./fetch` lève l'ambiguïté.

### Options

| Option | Effet |
|---|---|
| `--json` | Sortie JSON (CI, tableaux de bord) |
| `--html[=FICHIER]` | Rapport HTML autonome. Sans valeur : sortie standard. Précédence : `--html=FICHIER` > `html.path` de la config > stdout |
| `--qa` | Vérifie la présence des outils de QA du projet audité |
| `--coverage` | Lit un rapport de couverture existant + faits de présence de tests |
| `--baseline=FICHIER` | Compare à un instantané figé ; affiche nouvelles violations, aggravées, résolues |
| `--baseline-out=FICHIER` | Fige l'instantané de référence (voir *Baseline*) |
| `--fail-on-new` | Code 1 sur les seules **régressions** ; exige `--baseline` |
| `--fail-on-violations` | Code 1 si un seuil est dépassé, ou si un outil QA requis manque |
| `--config=FICHIER` | Fichier de configuration (défaut : `phpx-complexity.json`) |
| `--top=N` | Nombre de lignes du classement |
| `--exclude=FRAGMENT` | Exclut les chemins contenant FRAGMENT (**répétable**) |
| `--no-divergence` | Masque le rapport de divergence |
| `--keep` | *(fetch)* Conserve la copie temporaire du dépôt |
| `-h`, `--help` | Aide |

Une option non reconnue **est refusée** (code 2) plutôt qu'ignorée : une faute de
frappe comme `--jsno` ne doit pas rendre un rapport console en faisant croire à
la CI qu'elle reçoit du JSON.

### Codes de sortie

| Code | Signification |
|---:|---|
| `0` | Succès |
| `1` | Violations, en mode gate (`--fail-on-violations` ou `--fail-on-new`) |
| `2` | Erreur d'usage ou d'entrée-sortie |

### Recettes

```bash
# Audit lisible d'un projet
bin/phpx-complexity /chemin/vers/projet

# Sortie JSON pour la CI
bin/phpx-complexity /chemin/vers/projet --json > complexity.json

# Rapport HTML autonome (un seul fichier, ouvrable au double-clic)
bin/phpx-complexity /chemin/vers/projet --html=rapport.html

# Outillage QA et tests du projet audité
bin/phpx-complexity /chemin/vers/projet --qa --coverage

# Gate strict : échoue sur tout dépassement
bin/phpx-complexity /chemin/vers/projet --fail-on-violations

# Gate en cliquet : n'échoue que sur les régressions
bin/phpx-complexity /chemin/vers/projet --baseline=baseline.json --fail-on-new

# Auditer un dépôt distant
bin/phpx-complexity fetch https://github.com/vendor/projet.git --qa

# Restreindre le périmètre
bin/phpx-complexity src/ --top=10 --exclude=/Legacy/ --exclude=/generated/
```

---

## Baseline & deltas

Sur un projet existant, `--fail-on-violations` échoue dès le premier run : des
centaines de violations héritées que personne ne corrigera d'un coup, donc on
désactive le gate et il ne sert plus à rien. La baseline **accepte l'existant** et
ne fait échouer que ce qui **empire** — le modèle de PHPStan ou Psalm.

C'est aussi la réponse à la limite philosophique de l'outil : l'entropie
essentielle étant irréductible, on n'exige pas zéro complexité, on exige qu'elle
**ne régresse pas**.

```bash
# 1. Figer la référence (une fois, committée dans le dépôt)
bin/phpx-complexity src/ --baseline-out=baseline.json

# 2. Comparer l'état courant
bin/phpx-complexity src/ --baseline=baseline.json

# 3. Gate CI
bin/phpx-complexity src/ --baseline=baseline.json --fail-on-new
```

| Catégorie | Sens |
|---|---|
| **Nouvelles violations** | dépasse le seuil maintenant, pas dans la référence (méthode neuve incluse) |
| **Violations aggravées** | déjà au-dessus, valeur en hausse |
| **Violations résolues** | était au-dessus, ne l'est plus |
| **Apparues / disparues** | méthodes ajoutées ou supprimées, pour le contexte |

Une violation héritée **inchangée** n'apparaît nulle part : seul le mouvement est
montré. `--fail-on-new` sort en 1 sur les seules nouvelles et aggravées.

**Pourquoi `--baseline-out` plutôt que `--json`.** Un instantané est un
sous-ensemble du contrat `--json`, et une sortie `--json` complète reste une
référence valide. Mais celle-ci embarque les rangs centiles et la divergence, qui
sont **relatifs au lot** : ils changent pour toutes les méthodes dès qu'une seule
bouge. Mesuré sur ce dépôt en modifiant une seule méthode :

| | lignes changées | taille |
|---|---:|---:|
| sortie `--json` complète | 396 | 139 Ko |
| `--baseline-out` | **4** | 69 Ko |

Un diff de quatre lignes se revoit ; un diff de quatre cents se tamponne sans
lire — et une baseline qu'on ne relit plus ne protège plus rien.

Points de méthode :

- **L'identité d'une méthode est `fichier::méthode`**, jamais la ligne, qui se
  décale au moindre ajout en amont. Les homonymes d'un même fichier sont
  départagés par un rang. Un renommage apparaît en disparue + apparue.
- **Le classement se fait sur les seuils courants**, ceux que le gate doit
  imposer. Un seuil ayant bougé depuis l'instantané est signalé à part.
- **`baseline.json` se committe et se régénère volontairement**, jamais
  automatiquement — sans quoi le cliquet ne retient plus rien.

---

## Présence des outils de QA (`--qa`)

Audite l'**outillage qualité** du projet cible : analyse statique (PHPStan,
Psalm), standards (PHP-CS-Fixer, PHP_CodeSniffer), refactoring (Rector), tests
(PHPUnit, Pest, Behat, Infection), CI (GitHub Actions, GitLab CI), EditorConfig.

Chaque outil est détecté via `composer.json` (require / require-dev) **ou** la
présence de son fichier de configuration. La racine du projet est résolue en
remontant jusqu'au `composer.json`.

C'est un **fait de présence, pas un jugement**. Les outils déclarés `qa.required`
dans la configuration et manquants font échouer `--fail-on-violations`.

---

## Tests & couverture (`--coverage`)

La couverture est une mesure d'**exécution** : l'outil étant statique et
hors-ligne, il ne la calcule pas. `--coverage` produit donc deux blocs distincts :

1. **Couverture réelle** — lecture d'un rapport déjà généré par le projet (Clover
   de PHPUnit ou Cobertura), auto-détecté ou indiqué par `coverage.path`. Absence
   de rapport ⇒ **« non mesurée »**, jamais « 0 % », qui serait un verdict
   infondé.
2. **Présence de tests** (proxy statique) — nombre de classes et méthodes de
   test, et liste des classes source sans aucune classe `*Test`. C'est un
   **plancher**, pas de la couverture : qu'une classe `FooTest` existe ne prouve
   pas que `Foo` est testée utilement.

---

## Auditer un dépôt distant (`fetch`)

```bash
bin/phpx-complexity fetch https://github.com/vendor/projet.git
bin/phpx-complexity fetch git@github.com:vendor/projet.git --qa --keep
```

Clone le dépôt en superficiel dans un dossier temporaire, lui applique l'audit,
puis **nettoie — y compris si l'analyse échoue**. `--keep` conserve la copie et
affiche son chemin.

> **C'est la seule partie de l'outil qui accède au réseau.** Le cœur d'analyse ne
> connaît qu'un **chemin local** : la garantie hors-ligne de l'analyse elle-même
> reste entière. Le réseau est confiné à un wrapper opt-in qu'aucune option de
> l'audit ne peut déclencher.

**Accepté** : `https://`, `ssh://`, et la forme `git@hote:chemin`.
**Refusé** : `git://` (ni chiffré ni authentifié), `file://` et les chemins locaux
(un dossier local s'analyse directement), le transport `ext::` (exécution de
commande arbitraire) et tout ce qui commence par un tiret, que git prendrait pour
une option. La commande est passée en tableau — aucun shell, donc aucune
interpolation — avec `--` avant l'URL, hooks neutralisés et clone superficiel.

**Dépôts privés** : l'authentification est celle de git (agent SSH, credential
helper). Rien n'est réinventé et **aucune invite n'est posée** : un dépôt
inaccessible échoue immédiatement au lieu de faire attendre. Une clé protégée par
phrase de passe sans agent chargé échouera donc aussi.

**Limites** : `--coverage` a besoin d'un rapport **déjà généré** ; un clone seul
ne l'apporte pas, le module ne verra que la présence statique de tests. `--qa`
fonctionne pleinement. Nécessite le binaire `git`, dont l'absence est signalée.

---

## Configuration

Placer un `phpx-complexity.json` à la racine du projet audité (modèle :
`phpx-complexity.dist.json`) :

```json
{
    "thresholds": { "cognitive": 15, "params": 7, "returns": 3, "live_peak": 8, "entangle": 3 },
    "exclude": ["/vendor/", "/tests/"],
    "top": 25,
    "qa": { "required": ["phpstan", "phpunit"] },
    "coverage": { "path": "build/logs/clover.xml" },
    "html": { "path": "build/phpx-complexity.html" }
}
```

| Clé | Rôle |
|---|---|
| `thresholds` | Seuil par lentille |
| `exclude` | Fragments de chemin exclus |
| `top` | Taille du classement affiché |
| `qa.required` | Outils dont l'absence fait échouer le gate |
| `coverage.path` | Rapport de couverture à lire |
| `html.path` | Destination du rapport HTML |

Les fragments d'`exclude` se comparent au chemin **relatif à la racine auditée**,
jamais au chemin absolu : auditer un projet installé dans `/var/www/monprojet`
n'est donc pas vidé par le fragment `/var/`.

---

## Qualité du projet lui-même

Le projet s'applique les exigences qu'il audite :

```bash
scripts/qa.sh    # CS-Fixer (PSR-12) + PHPStan level 9 + PHPUnit + couverture ≥ 90 %
composer qa      # idem
```

Garde-fou à lancer avant chaque commit ; un code de sortie non nul signale un
commit à corriger. Le script inclut l'outil **appliqué à son propre code en mode
cliquet** : les dépassements hérités sont figés dans `baseline.json` et passent,
toute régression échoue. Tout est hors-ligne.

La couverture est gatée à 90 % quand un pilote (Xdebug ou PCOV) est disponible ;
sans pilote, les tests tournent sans elle plutôt que d'échouer — mieux vaut un
garde-fou partiel qu'un garde-fou contourné.

**`composer.lock` est versionné** et les deux outils qui peuvent faire échouer le
gate sont contraints au patch (`~3.95.0`, `~2.2.0`). Sans cela, une version
mineure de PHPStan apportant de nouvelles règles casse la CI sans qu'une ligne de
code ait bougé. Pour une bibliothèque, ce verrou n'engage que le développement :
le lock d'un paquet est ignoré par ses consommateurs. Absorber une montée de
version est donc un geste **délibéré** — `composer update`, relancer
`scripts/qa.sh`, traiter les nouveaux signalements, committer le lock.

**État actuel** : PHPStan level 9 sans erreur, PSR-12 respecté, 169 tests,
**95 % de couverture**, dépassements hérités figés.

### Outils d'étude

| Script | Rôle |
|---|---|
| `tools/corpus-study.sh` | Récupère un corpus de projets PHP publics (mis en cache) et fige un audit par projet |
| `tools/corpus-report.php` | Agrège : distributions, corrélations de rangs, rendement marginal |
| `tools/live-peak-variant.php` | Rejoue la variante « paramètres exclus » de `live_peak`, mesurée puis écartée |
| `tools/coverage-gate.php` | Vérifie un rapport clover contre un plancher |
| `tools/build-phar.sh` | Construit le PHAR distribuable |

---

## Build PHAR

```bash
composer require --working-dir=var/box humbug/box:^4.7
tools/build-phar.sh
```

produit `phpx-complexity.phar`, binaire unique distribuable (~320 Ko). Le script
lève `phar.readonly` le temps du build et bascule sur les dépendances de
production seules — sinon PHPUnit et PHPStan partiraient dans l'archive — puis
restaure l'environnement de développement quoi qu'il arrive.

---

## Origine

Les lentilles `params` (S107) et `returns` (S1142) dérivent de règles PHPStan
custom Codeam, réimplémentées ici sans couplage PHPStan.

Sous licence MIT.
