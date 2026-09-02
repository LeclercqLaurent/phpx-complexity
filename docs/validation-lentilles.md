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

> Les distributions et corrélations ci-dessous sont indépendantes des seuils.
> Seules les sections « seuils » et « rendement marginal » en dépendent ; elles
> reflètent l'état **après** l'abaissement d'`entangle` de 4 à 3 décidé au vu de
> cette étude.

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
| `entangle` | 0 | 0 | 1,33 | 2 | 3,06 | 3 | **1,02 %** |

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
| `entangle` | ~~4~~ → **3** | ≈ p99 après correction | **corrigé** — voir ci-dessous |

Le seuil d'origine, 4, se situait **au-delà du 99,8ᵉ centile** (p99 = 3,06) et ne
signalait que **85 méthodes sur 40 594** (0,21 %), soit un ordre de grandeur de
plus que les autres lentilles. La lentille était **quasi inerte**.

La sonde le montrait concrètement : `tangled`, méthode écrite exprès pour
entremêler six paramètres par des flux de données, plafonnait à **2,67** — donc
sous le seuil. Une lentille qui ne se déclenche pas sur son propre cas d'école a
un seuil mal placé, pas une mesure fausse.

**Le seuil est passé à 3** (≈ p99). Mesure refaite sur le même corpus :

| | seuil 4 | seuil 3 |
|---|---:|---:|
| méthodes signalées | 85 (0,21 %) | **415 (1,02 %)** |
| dont hors radar S3776 | 17 (20,0 %) | **161 (38,8 %)** |

Le rendement marginal **double**, et la part de méthodes signalées rejoint celle
des autres lentilles (0,6 à 3,4 %). C'était donc bien le seuil, pas la mesure.

## 3. Rendement marginal — l'inconfort

Part des méthodes signalées par chaque lentille que **S3776 ne signale pas**,
c'est-à-dire ce qu'un linter mono-métrique laisserait passer :

| lentille | signalées | hors radar S3776 | part |
|---|---:|---:|---:|
| `params` (S107) | 245 | 200 | **81,6 %** |
| `returns` (S1142) | 1 370 | 922 | **67,3 %** |
| `entangle` (seuil 3) | 415 | 161 | 38,8 % |
| `live_peak` | 530 | 114 | 21,5 % |

**`live_peak` reste la lentille au plus faible rendement marginal** : près de 80 %
de ce qu'elle signale est déjà signalé par S3776. C'est cohérent avec sa
corrélation à `params` relevée plus haut — elle capte en partie ce que S107
mesure déjà, et le reste recoupe largement S3776.

`entangle`, une fois son seuil corrigé, se place **devant `live_peak`** et
apporte 161 découvertes qu'un linter mono-métrique laisserait passer. Illustration
sur le code de l'outil lui-même, où le nouveau seuil surface trois méthodes :

```
Cli/Application::audit                    entangle=4.00  cognitive=2
Baseline/BaselineComparator::categorize   entangle=3.60  cognitive=7
Report/ConsoleReporter::divergenceHint    entangle=3.20  cognitive=8
```

Les trois ont une complexité cognitive **basse** : ce sont exactement les angles
morts de S3776 que la lentille existe pour révéler. Elles sont figées dans la
baseline plutôt que réécrites — les découper ne ferait que **déplacer**
l'intrication, ce que le garde-fou n°3 interdit de récompenser.

## 4. La variante « paramètres exclus » — mesurée et écartée

L'étude laissait ouverte une piste : exclure les paramètres du calcul de vivacité
pour coller à l'intention affichée (« la pression interne, pas la signature »).
Elle a été implémentée et mesurée sur le même corpus (`tools/live-peak-variant.php`),
seuils placés **au même centile** (p98,2) pour que la comparaison porte sur la
mesure et non sur une sévérité différente : `live_peak > 8` contre
`live_internal > 6`.

### Corrélations de rangs

| | `params` | `cognitive` | `entangle` |
|---|---:|---:|---:|
| `live_peak` (actuel) | 0,703 | 0,637 | 0,715 |
| `live_internal` (variante) | **0,287** | **0,708** | **0,825** |

### Rendement au seuil équivalent

| | signalées | hors S3776 | hors S3776 **et** S107 |
|---|---:|---:|---:|
| `live_peak` | 530 | 114 (21,5 %) | 102 (19,2 %) |
| `live_internal` | 601 | 136 (22,6 %) | 135 (22,5 %) |

### Verdict : non adoptée

La variante **fait ce qu'elle promet** — la corrélation à S107 s'effondre de 0,703
à 0,287, elle cesse donc réellement de remesurer la signature. Mais elle le paie
partout ailleurs : elle se **rapproche** de S3776 (0,637 → 0,708) et surtout de
l'intrication (0,715 → **0,825**), au point que les deux lentilles maison
deviendraient largement redondantes entre elles — précisément ce que le garde-fou
n°1 interdit. Le gain en découvertes propres, lui, est marginal : 21,5 % → 22,6 %
hors S3776.

**La redondance serait déplacée, pas supprimée.** C'est, transposé aux métriques,
le travers que le garde-fou n°3 dénonce pour le code : un découpage qui déplace
l'entropie hors du champ de mesure ne l'a pas réduite.

**Ce qui a donc été corrigé, c'est la documentation, pas la mesure.** Pour le
lecteur, un paramètre qu'il faut garder en tête occupe bel et bien la mémoire de
travail : les inclure est fidèle à la justification 7±2. L'erreur était la
prétention « ≠ S107 », pas le calcul. Le signal tient à la **paire** avec
l'intrication, seule capable de séparer une signature large d'un enchevêtrement
réel.

`tools/live-peak-variant.php` est conservé pour rejouer l'expérience si un élément
nouveau la justifiait.

## Ce qu'il faut en retenir

1. La **divergence entre lentilles est réelle et mesurable** : le cœur de l'outil
   est validé.
2. `live_peak` **passe** la non-redondance mais **recoupe S107 plus que S3776** :
   sa documentation surestime son orthogonalité.
3. `entangle` **discrimine bien** le plat de l'enchevêtré ; son seuil d'origine
   le rendait inerte, **ramené à 3** il devient la plus utile des deux lentilles
   maison (38,8 % de découvertes propres contre 21,5 %).
4. `live_peak` reste la lentille au plus faible rendement marginal et **n'est pas
   orthogonale à S107** — c'est désormais assumé et documenté. La variante qui
   aurait corrigé ce point a été mesurée puis **écartée** : elle déplaçait la
   redondance vers S3776 et l'intrication.
5. Aucune nouvelle lentille ne devrait être ajoutée sans repasser par ce
   protocole : le garde-fou de non-redondance est déjà sévère pour l'existant, et
   l'outillage de mesure est maintenant en place pour l'appliquer.
