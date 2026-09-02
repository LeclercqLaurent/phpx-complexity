# Validation empirique des lentilles maison

Les garde-fous du projet imposent, avant d'ajouter une métrique, de vérifier
**(1)** qu'elle ne classe pas comme S3776 — sinon c'est S3776 repeint, donc du
bruit — et **(2)** que son seuil se défend autrement que par l'intuition.
`live_peak` et `entangle` reposaient jusqu'ici sur un raisonnement, pas sur des
mesures. Ce document comble ce trou.

## Méthode

Corpus de **40 594 méthodes** issues de **10 projets PHP publics**, récupérés par
la sous-commande `fetch` et audités avec les seuils par défaut. Code de test
exclu (non représentatif).

```bash
tools/corpus-study.sh     # récupère le corpus, fige un --json par projet
php tools/corpus-report.php
```

| Projet | Méthodes | | Projet | Méthodes |
|---|---:|---|---|---:|
| laravel/framework | 13 232 | | composer/composer | 2 409 |
| phpstan/phpstan-src | 12 167 | | doctrine/orm | 2 326 |
| sebastianbergmann/phpunit | 5 268 | | thephpleague/flysystem | 1 417 |
| nikic/PHP-Parser | 1 259 | | symfony/console | 1 180 |
| Seldaek/monolog | 693 | | guzzle/guzzle | 643 |

## Distribution réelle

| lentille | p50 | p75 | p90 | p95 | p99 | seuil | % > seuil |
|---|---:|---:|---:|---:|---:|---:|---:|
| `cognitive` | 0 | 1 | 5 | 10 | 36 | 15 | 3,06 % |
| `params` | 1 | 2 | 3 | 4 | 7 | 7 | 0,60 % |
| `returns` | 1 | 1 | 2 | 3 | 6 | 3 | 3,37 % |
| `live_peak` | 1 | 2 | 4 | 5 | 9 | 8 | **1,31 %** |
| `entangle` | 0 | 0 | 1,33 | 2 | 3,06 | 4 | **0,21 %** |

La médiane de `cognitive` est **0** : la majorité des méthodes PHP publiées sont
triviales (accesseurs, délégations). Tous les seuils vivent donc loin dans la
queue de distribution, ce qui est attendu.

## 1. Non-redondance — corrélation de rangs (Spearman)

|  | cognitive | params | returns | live_peak | entangle |
|---|---:|---:|---:|---:|---:|
| **cognitive** | 1,000 | 0,334 | 0,431 | 0,637 | 0,651 |
| **params** | 0,334 | 1,000 | −0,019 | **0,703** | 0,402 |
| **returns** | 0,431 | −0,019 | 1,000 | 0,212 | 0,308 |
| **live_peak** | 0,637 | 0,703 | 0,212 | 1,000 | 0,715 |
| **entangle** | 0,651 | 0,402 | 0,308 | 0,715 | 1,000 |

**La thèse de l'outil tient.** Les lentilles classent réellement différemment
(ρ de −0,02 à 0,72) : une métrique isolée a donc bien des angles morts, et le
rapport de divergence mesure quelque chose de réel.

**Les deux lentilles maison passent la non-redondance**, sans triomphe : ρ ≈ 0,64
avec S3776 laisse environ 60 % de variance non expliquée. Elles ne sont pas des
copies, elles ne sont pas non plus indépendantes.

**Réserve importante** : `live_peak` corrèle **davantage avec `params` (0,703)
qu'avec `cognitive` (0,637)**, alors que sa documentation affirme mesurer « la
pression interne, pas la signature (≠ S107) ». Le mécanisme est vérifié : un
paramètre *utilisé dans le corps* est une variable vivante, donc une signature
large gonfle mécaniquement le pic.

```
méthode      params  live_peak  entangle
plate             6          6      0.00   ← 6 paramètres, aucune logique
internal          0          6      0.00   ← 6 locales, aucun flux
tangled           6          4      2.67   ← 6 paramètres réellement combinés
unused            6          0      0.00   ← paramètres jamais utilisés
```

La conception avait anticipé ce point : `live_peak` doit se lire **en regard de**
`entangle`, et c'est bien ce que montre la sonde — `plate` et `tangled` ont le
même nombre de paramètres, seul `entangle` les sépare. **C'est donc la paire qui
distingue l'entropie plate de l'entropie enchevêtrée, pas `live_peak` seul.** La
formulation « ≠ S107 » de la lentille mériterait d'être nuancée en ce sens.

## 2. Seuils

| lentille | seuil | position empirique | verdict |
|---|---:|---|---|
| `live_peak` | 8 | ≈ p98,7 (p99 = 9) | **défendable** — cohérent avec les autres lentilles (0,6 à 3,4 % signalés) et avec la justification 7±2, qui tombe juste |
| `entangle` | 4 | **au-delà de p99,8** (p99 = 3,06) | **indéfendable en l'état** |

`entangle > 4` ne signale que **85 méthodes sur 40 594** (0,21 %), soit un ordre
de grandeur de plus que les autres lentilles. La lentille est **quasi inerte**.

La sonde le montre concrètement : `tangled`, méthode écrite exprès pour entremêler
six paramètres par des flux de données, plafonne à **2,67** — donc sous le seuil.
Une lentille qui ne se déclenche pas sur son propre cas d'école a un seuil mal
placé, pas une mesure fausse.

**Recommandation : abaisser le seuil de `entangle` à 3** (≈ p99), ce qui le
ramènerait autour de 1 % de méthodes signalées, en ligne avec les autres.

## 3. Rendement marginal — l'inconfort

Part des méthodes signalées par chaque lentille que **S3776 ne signale pas**,
c'est-à-dire ce qu'un linter mono-métrique laisserait passer :

| lentille | signalées | hors radar S3776 | part |
|---|---:|---:|---:|
| `params` (S107) | 245 | 200 | **81,6 %** |
| `returns` (S1142) | 1 370 | 922 | **67,3 %** |
| `live_peak` | 530 | 114 | 21,5 % |
| `entangle` | 85 | 17 | 20,0 % |

**Les deux lentilles maison apportent moins de découvertes marginales que les
deux règles SonarQube qu'elles étaient censées compléter.** Près de 80 % de ce
que signale `live_peak` est déjà signalé par S3776.

À nuancer : ce rendement dépend directement des seuils, et celui d'`entangle` est
justement trop haut — le chiffre de 20 % porte sur 85 méthodes seulement. Une
reprise de la mesure après ajustement du seuil est nécessaire avant de conclure
sur la valeur réelle de la lentille.

## Ce qu'il faut en retenir

1. La **divergence entre lentilles est réelle et mesurable** : le cœur de l'outil
   est validé.
2. `live_peak` **passe** la non-redondance mais **recoupe S107 plus que S3776** :
   sa documentation surestime son orthogonalité.
3. `entangle` **discrimine bien** le plat de l'enchevêtré, mais son **seuil de 4
   le rend inerte** ; à corriger avant tout jugement sur son utilité.
4. Aucune nouvelle lentille ne devrait être ajoutée avant que ces deux-là soient
   réglées : le garde-fou de non-redondance leur est déjà sévère.
