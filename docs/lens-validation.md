# Empirical validation of the in-house lenses

Before a metric is added, the project's guards require checking **(1)** that it
does not rank like S3776, because if it does it is S3776 repainted and therefore
noise, and **(2)** that its threshold is defensible on something other than
intuition. Until now `live_peak` and `entangle` rested on an argument, not on
measurements. This document fills that gap.

## Method

A corpus of **40,594 methods** from **10 public PHP projects**, fetched through
the `fetch` subcommand and audited with the default thresholds. Test code
excluded, being unrepresentative.

> The distributions and correlations below are independent of the thresholds.
> Only the "thresholds" and "marginal yield" sections depend on them, and they
> reflect the state **after** the lowering of `entangle` from 4 to 3 decided in
> the light of this study.
>
> **Re-measured on 2026-09-03**, after fixing two deviations of
> `CognitiveComplexityLens` from the S3776 spec (labelled jumps not counted,
> two-word `else if` over-counted). The study was therefore built on a partially
> faulty implementation; the check showed that **129 methods out of 40,575 change
> value (0.32%)** and that no conclusion moves: correlations identical to the
> third decimal, the share of `cognitive` above the threshold going from 3.06% to
> 3.05%. The figures below are the post-fix ones.

```bash
tools/corpus-study.sh     # fetches the corpus, freezes one --json per project
php tools/corpus-report.php
```

| Project | Methods | | Project | Methods |
|---|---:|---|---|---:|
| laravel/framework | 13,232 | | composer/composer | 2,409 |
| phpstan/phpstan-src | 12,167 | | doctrine/orm | 2,326 |
| sebastianbergmann/phpunit | 5,268 | | thephpleague/flysystem | 1,417 |
| nikic/PHP-Parser | 1,259 | | symfony/console | 1,180 |
| Seldaek/monolog | 693 | | guzzle/guzzle | 643 |

## The real distribution

| lens | p50 | p75 | p90 | p95 | p99 | threshold | % > threshold |
|---|---:|---:|---:|---:|---:|---:|---:|
| `cognitive` | 0 | 1 | 5 | 10 | 36 | 15 | 3.05% |
| `params` | 1 | 2 | 3 | 4 | 7 | 7 | 0.60% |
| `returns` | 1 | 1 | 2 | 3 | 6 | 3 | 3.37% |
| `live_peak` | 1 | 2 | 4 | 5 | 9 | 8 | **1.31%** |
| `entangle` | 0 | 0 | 1.33 | 2 | 3.06 | 3 | **1.02%** |

The median of `cognitive` is **0**: most published PHP methods are trivial
(accessors, delegations). Every threshold therefore lives far into the tail of
the distribution, which is what one would expect.

## 1. Non-redundancy: rank correlation (Spearman)

|  | cognitive | params | returns | live_peak | entangle |
|---|---:|---:|---:|---:|---:|
| **cognitive** | 1.000 | 0.334 | 0.431 | 0.637 | 0.651 |
| **params** | 0.334 | 1.000 | -0.019 | **0.703** | 0.402 |
| **returns** | 0.431 | -0.019 | 1.000 | 0.212 | 0.308 |
| **live_peak** | 0.637 | 0.703 | 0.212 | 1.000 | 0.715 |
| **entangle** | 0.651 | 0.402 | 0.308 | 0.715 | 1.000 |

**The thesis of the tool holds.** The lenses really do rank differently (rho from
-0.02 to 0.72), so an isolated metric does have blind spots, and the divergence
report measures something real.

**Both in-house lenses pass the non-redundancy guard**, without triumph: a rho of
about 0.64 against S3776 leaves roughly 60% of the variance unexplained. They are
not copies, and they are not independent either.

**An important reservation**: `live_peak` correlates **more with `params` (0.703)
than with `cognitive` (0.637)**, whereas its documentation claimed to measure
"internal pressure, not the signature (unlike S107)". The mechanism was checked: a
parameter *used in the body* is a live variable, so a wide signature mechanically
inflates the peak.

```
method       params  live_peak  entangle
flat              6          6      0.00   <- 6 parameters, no logic at all
internal          0          6      0.00   <- 6 locals, no flow
tangled           6          4      2.67   <- 6 parameters really combined
unused            6          0      0.00   <- parameters never used
```

The design had anticipated this: `live_peak` is meant to be read **alongside**
`entangle`, and that is exactly what the probe shows. `flat` and `tangled` have
the same number of parameters, and only `entangle` separates them. **It is
therefore the pair that distinguishes flat entropy from interwoven entropy, not
`live_peak` alone.** The lens's "unlike S107" wording deserved to be qualified
accordingly.

## 2. Thresholds

| lens | threshold | empirical position | verdict |
|---|---:|---|---|
| `live_peak` | 8 | about p98.7 (p99 = 9) | **defensible**, consistent with the other lenses (0.6% to 3.4% flagged) and with the 7±2 justification, which lands right |
| `entangle` | ~~4~~ -> **3** | about p99 after the fix | **corrected**, see below |

The original threshold, 4, sat **beyond the 99.8th percentile** (p99 = 3.06) and
flagged only **85 methods out of 40,594** (0.21%), an order of magnitude fewer
than the other lenses. The lens was **almost inert**.

The probe showed it concretely: `tangled`, a method written on purpose to
interweave six parameters through data flows, topped out at **2.67**, therefore
below the threshold. A lens that does not fire on its own textbook case has a
misplaced threshold, not a wrong measurement.

**The threshold moved to 3** (about p99). Re-measured on the same corpus:

| | threshold 4 | threshold 3 |
|---|---:|---:|
| methods flagged | 85 (0.21%) | **415 (1.02%)** |
| of which off the S3776 radar | 17 (20.0%) | **163 (39.3%)** |

The marginal yield **doubles**, and the share of flagged methods rejoins that of
the other lenses (0.6% to 3.4%). It was indeed the threshold, not the measurement.

## 3. Marginal yield: the uncomfortable part

The share of methods flagged by each lens that **S3776 does not flag**, that is
what a single-metric linter would let through:

| lens | flagged | off the S3776 radar | share |
|---|---:|---:|---:|
| `params` (S107) | 245 | 200 | **81.6%** |
| `returns` (S1142) | 1,370 | 922 | **67.3%** |
| `entangle` (threshold 3) | 415 | 163 | 39.3% |
| `live_peak` | 530 | 114 | 21.5% |

**`live_peak` remains the lens with the lowest marginal yield**: nearly 80% of
what it flags is already flagged by S3776. That is consistent with its
correlation to `params` noted above, since it partly captures what S107 already
measures and the rest largely overlaps S3776.

`entangle`, once its threshold was corrected, places **ahead of `live_peak`** and
brings 163 findings a single-metric linter would let through. Illustrated on the
tool's own code, where the new threshold surfaces three methods:

```
Cli/Application::audit                    entangle=4.00  cognitive=2
Baseline/BaselineComparator::categorize   entangle=3.60  cognitive=7
Report/ConsoleReporter::divergenceHint    entangle=3.20  cognitive=8
```

All three have a **low** cognitive complexity: they are precisely the S3776 blind
spots the lens exists to reveal. They are frozen in the baseline rather than
rewritten, because splitting them would only **displace** the entanglement, which
guard number 3 forbids rewarding.

## 4. The "parameters excluded" variant: measured and discarded

The study left one avenue open: excluding parameters from the liveness
computation, to match the stated intent ("internal pressure, not the signature").
It was implemented and measured on the same corpus
(`tools/live-peak-variant.php`), with thresholds placed **at the same percentile**
(p98.2) so the comparison bears on the measurement rather than on a different
severity: `live_peak > 8` against `live_internal > 6`.

### Rank correlations

| | `params` | `cognitive` | `entangle` |
|---|---:|---:|---:|
| `live_peak` (current) | 0.703 | 0.637 | 0.715 |
| `live_internal` (variant) | **0.287** | **0.708** | **0.825** |

### Yield at the equivalent threshold

| | flagged | outside S3776 | outside S3776 **and** S107 |
|---|---:|---:|---:|
| `live_peak` | 530 | 114 (21.5%) | 102 (19.2%) |
| `live_internal` | 601 | 136 (22.6%) | 135 (22.5%) |

### Verdict: not adopted

The variant **does what it promises**: the correlation to S107 collapses from
0.703 to 0.287, so it really does stop re-measuring the signature. But it pays
for that everywhere else. It moves **closer** to S3776 (0.637 to 0.708) and above
all to entanglement (0.715 to **0.825**), to the point where the two in-house
lenses would become largely redundant with each other, which is exactly what
guard number 1 forbids. The gain in findings of its own is marginal: 21.5% to
22.6% outside S3776.

**The redundancy would be displaced, not removed.** Transposed to metrics, this is
the very flaw guard number 3 denounces for code: a split that moves entropy out of
the field of measurement has not reduced it.

**What was corrected is therefore the documentation, not the measurement.** For the
reader, a parameter that has to be kept in mind does occupy working memory, so
including them is faithful to the 7±2 justification. The mistake was the "unlike
S107" claim, not the computation. The signal lies in the **pair** with
entanglement, which alone can separate a wide signature from real interweaving.

`tools/live-peak-variant.php` is kept so the experiment can be replayed should
something new justify it.

## What to take away

1. The **divergence between lenses is real and measurable**: the core of the tool
   is validated.
2. `live_peak` **passes** non-redundancy but **overlaps S107 more than S3776**:
   its documentation overstated its orthogonality.
3. `entangle` **discriminates well** between the flat and the interwoven; its
   original threshold made it inert, and **brought down to 3** it becomes the more
   useful of the two in-house lenses (38.8% of findings of its own against 21.5%).
4. `live_peak` remains the lens with the lowest marginal yield and **is not
   orthogonal to S107**, which is now accepted and documented. The variant that
   would have fixed that point was measured then **discarded**: it displaced the
   redundancy towards S3776 and entanglement.
5. No new lens should be added without going through this protocol again: the
   non-redundancy guard is already severe on what exists, and the measuring
   tooling is now in place to apply it.
