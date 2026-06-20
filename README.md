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
    "top": 25
}
```

## Build PHAR

```bash
composer global require humbug/box
box compile
```

produit `phpx-complexity.phar`, binaire unique distribuable.

## Origine

Les lentilles `params` (S107) et `returns` (S1142) dérivent de règles PHPStan
custom Codeam, réimplémentées ici sans couplage PHPStan. Sous licence MIT.
