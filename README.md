# phpx-complexity

A **multi-lens**, **offline** PHP complexity auditor with a single dependency
(`nikic/php-parser`). It natively reproduces the SonarQube "complexity" family
of
rules, adds two lenses of its own, then **plays the lenses against each other**
to
reveal what any isolated metric lets through.

[![CI](https://github.com/LeclercqLaurent/phpx-complexity/actions/workflows/ci.yml/badge.svg)](https://github.com/LeclercqLaurent/phpx-complexity/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.2-777BB4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Coverage](https://img.shields.io/badge/coverage-95%25-brightgreen)](#the-quality-of-the-project-itself)

PHP >= 8.2 · MIT · `codeam/phpx-complexity` · single dependency:
`nikic/php-parser`

---

## Why another complexity tool

Traditional QA metrics measure complexity one axis at a time. This tool looks
for **disagreement between several of them**, because a method that one metric
finds simple and another finds hard is usually where a single-metric linter lets
a problem through.

## The idea in one example

Both methods below have a cognitive complexity (S3776) of 2 and take one
parameter. Any Sonar-style linter treats them the same.

```php
// Method A: four independent values, assembled at the end
public function assemble(Order $order): Report
{
    $customer = $this->customers->find($order->customerId());
    $invoice  = $this->invoices->latest();
    $shipping = $this->carriers->cheapest();
    $history  = $this->history->recent();

    if ($order->isGift())    { $invoice = $invoice->withoutPrices(); }
    if ($order->isExpress()) { $shipping = $shipping->expedited(); }

    return new Report($customer, $invoice, $shipping, $history);
}

// Method B: three values that keep referring to one another
public function settle(Order $order): Money
{
    $base     = $this->pricer->price($order->items());
    $discount = $order->isGift() ? $base * 0.5 : 0.0;
    $tax      = ($base - $discount) * $this->rate($order->country());

    if ($order->isExpress()) {
        $tax = $tax + ($base - $discount) * 0.02;
    }

    return $base - $discount + $tax;
}
```

| Lens        | Method A | Method B |
|-------------|----------|----------|
| `cognitive` | 2        | 2        |
| `params`    | 1        | 1        |
| `returns`   | 1        | 1        |
| `live_peak` | 5        | 4        |
| `entangle`  | 0.4      | 3.0      |

Four lenses out of five say these methods are the same, and `live_peak` even
gives the edge to B, which holds one variable fewer. Only `entangle` separates
them, and it does so by a factor of seven.

The difference is real. In A you can read each line, forget it, and move on:
nothing you computed on line 3 is needed on line 5. In B, `$base`, `$discount`
and `$tax` keep referring to one another, so to understand the last line you
have
to hold all three at once, along with how the express branch has already changed
one of them. Same branch count, different reading load.

That gap is what `entangle` measures, and what the divergence report surfaces.
It
is also the whole argument for crossing lenses rather than trusting one: a
single-metric linter reports nothing here.

> The figures above come from running the tool on these two snippets. Every
> number in this README is measured rather than asserted, which is the least a
> measuring tool owes its reader.

## The frame: QA as an entropy policy

We use *entropy* as a conceptual lens inspired by information theory, not as a
literal Shannon measure: roughly, how much a reader has to hold in mind to
describe what a method does. Each QA rule bounds one facet of it. S3776 bounds
execution paths, S107 the degrees of freedom on input, S1142 the exit points.

The naive reading, "QA lowers entropy", does not hold: taken literally, the best
code would be the code that does nothing. Following Brooks (*No Silver Bullet*),
we distinguish:

- **Essential** complexity, which comes from the problem and cannot be removed;
- **Accidental** complexity, added without necessity, which DRY, KISS and YAGNI exist to remove.

QA, on this view, is a policy of **compression and localisation**: remove the
accidental, and cut the essential into packets that fit under a reader's
cognitive threshold. It is not a war on complexity.

Three design decisions follow directly from this frame.

**1. The standard rules are missing an axis.** S3776 counts branches but is blind to dependency between symbols. Two in-house lenses fill that gap:

| Key          | Measures                                                             | Default threshold |
|--------------|----------------------------------------------------------------------|-------------------|
| `live_peak`  | Peak number of simultaneously live variables (working memory, 7 ± 2) | 8                 |
| `entangle`   | Average degree of the variable co-occurrence graph                   | 3                 |

They are read together: a high peak with low entanglement (a factory assembling
8 fields) is fine; a high peak with high entanglement is not. Of the two,
`entangle` is the genuinely new signal. `live_peak` counts parameters used in
the body, so it correlates with S107 (ρ = 0.70); we keep it because an argument
you must remember does occupy working memory, but we do not claim it is
orthogonal.

**2. Zero is not the target; unexplained growth is.** Since essential complexity cannot be removed, an absolute threshold cannot be enforced on existing code. Hence the **ratchet mode**: accept the current state, fail only on regressions.

**3. No aggregate score.** The tool never prints a grade, per method or overall, in any output format, and tests enforce this. It reports raw values and comparisons to thresholds. To be precise about what is and is not factual here: the *thresholds* are opinions, made explicit and configurable; the *comparison* to a chosen threshold is a fact; a single number like "7.5/10" would blend the two while hiding the blend.

There is a second, more fundamental reason not to aggregate. A formula like
`cognitive + live_peak + entangle` assumes an exchange rate: why should one
level of nesting be worth one live variable? Why should some amount of
entanglement offset some amount of control-flow complexity? These questions have
no general answer. Keeping the lenses separate avoids inventing an arbitrary
equivalence between phenomena of different kinds.

The divergence report does order methods, by how far their percentile ranks
disagree across lenses, but that ordering is a triage aid, not a quality claim.

## The five lenses

| Key         | Reference | Measures                                                        | Default |
|-------------|-----------|-----------------------------------------------------------------|---------|
| `cognitive` | S3776     | Cognitive complexity: branching, nesting, logical-operator runs | 15      |
| `params`    | S107      | Parameters in the signature                                     | 7       |
| `returns`   | S1142     | Return statements                                               | 3       |
| `live_peak` |           | Peak of simultaneously live variables                           | 8       |
| `entangle`  |           | Average degree of the variable co-occurrence graph              | 3       |

The first three are native reimplementations on top of php-parser (no PHPStan).
Their conformance is tested case by case against values derived from the
published rules, not from our own output, so that a wrong reimplementation
actually fails a test (`tests/Lens/*ConformanceTest.php`).

Documented deviations: recursion is not counted (it would need interprocedural
analysis); `match` is treated as a `switch` (it postdates the spec); `else { if
… }` counts as `else if`, because php-parser produces the same tree and PHP
itself does not distinguish `else if` from `elseif`.

The two in-house lenses have no external specification, so their definition *is*
the specification and has to be just as precise. For `live_peak`: when a
variable becomes live, when it stops being live, how branches, loops, closures
and arrow functions, `$this` and properties, and exceptional control flow are
handled. For `entangle`: what counts as a co-occurrence and at what granularity.
These definitions are fixed in `docs/lenses.md` and covered by fixtures; a
metric that cannot be explained precisely cannot serve as an engineering
constraint.

A note on `returns`: the default of 3 follows S1142, but it penalises
guard-clause style. If your codebase uses early returns for invariant checks,
raise it; the ratchet will still catch growth.

## Why cross the lenses

An isolated complexity metric has blind spots. Cognitive complexity (S3776)
counts branches, but ignores a method with no `if` at all that interweaves ten
variables. Parameter count (S107) ignores what happens inside. Return count
(S1142) says nothing about what precedes each `return`. Each is blind to what
the others see.

Crossing several lenses surfaces the methods where they contradict each other,
and that is where the problems a single-metric linter lets through are hiding.
Disagreement between lenses is the signal.

This is not a hunch: measured over 40,594 methods from 10 public PHP projects,
rank correlations between lenses range from −0.02 to 0.72. They really do rank
methods differently. Details in `docs/lens-validation.md`.

## The divergence report

For each lens, methods are ranked by percentile within the analysed codebase.
The report lists methods whose ranks disagree most across lenses: low on
`cognitive`, high on `entangle`, for instance. Disable with `--no-divergence`.

Because ranks are relative to the codebase, the report is a triage tool for
*this* project, not a comparison between projects.

The configurations worth a human look, in decreasing order of interest:

- low `cognitive`, high `live_peak` or `entangle`: complexity that S3776 does not see;
- high `live_peak` and high `entangle` together: state that is both plentiful *and* interdependent;
- several lenses above their threshold at once;
- high `live_peak`, low `entangle`: usually benign (assembly, factories); check once, then ignore.

These are signals for review, not proof that a rewrite is needed.

## What the evidence shows

The correlations in the previous section establish that the lenses diverge; they
do not yet establish that divergence predicts defects or review time. That is
the open question, and the reason the tool reports rather than grades. If you
can correlate divergence with change frequency or bug density on your own code,
we would like to hear about it.

## Design principles

- **Measure, don't judge.** Values and thresholds, never a grade.
- **Keep dimensions separate.** No exchange rate between different phenomena.
- **Signals, not verdicts.** A warning points at something to examine; the decision stays with the developer.
- **Reduce the accidental, preserve the essential.** A hard problem is allowed to produce a hard method; the tool should make that visible, not punish it.
- **Monotonic improvement.** What the ratchet has accepted must not silently degrade.
- **Reproducible metrics.** Every lens has a precise definition, documented deviations, and tests derived from its specification.

---

## Installation

### The PHAR (recommended)

A single binary with no dependency to install: the most convenient form for
auditing a third-party project without polluting its `composer.json`.

```bash
curl -sSLo phpx-complexity.phar \
  https://github.com/LeclercqLaurent/phpx-complexity/releases/latest/download/phpx-complexity.phar
chmod +x phpx-complexity.phar
./phpx-complexity.phar --help
```

PHARs are published on the [releases
page](https://github.com/LeclercqLaurent/phpx-complexity/releases)
and can be rebuilt from source (see *Building the PHAR*).

### From a clone of the repository

```bash
git clone https://github.com/LeclercqLaurent/phpx-complexity.git
cd phpx-complexity
composer install
bin/phpx-complexity --help
```

> The package is **not published on Packagist** yet, so `composer require
> codeam/phpx-complexity` will not work. The name is the one declared in
> `composer.json` for the day it is.

---

## Commands

### General form

```
phpx-complexity [audit] [PATH] [options]
phpx-complexity fetch URL [options]
```

The verb is optional: `audit` is implicit, `fetch` is the only explicit one.
With
no path, the current directory is audited.

> A directory literally named `fetch` or `audit` would be taken for a verb:
> writing `./fetch` removes the ambiguity.

### Options

| Option | Effect |
|---|---|
| `--json` | JSON output (CI, dashboards) |
| `--html[=FILE]` | Standalone HTML report. With no value: standard output. Precedence: `--html=FILE` > `html.path` from the config > stdout |
| `--qa` | Checks that the audited project's QA tools are present |
| `--coverage` | Reads an existing coverage report plus static facts about test presence |
| `--baseline=FILE` | Compares against a frozen snapshot; reports new, worsened and resolved violations |
| `--baseline-out=FILE` | Freezes the reference snapshot (see *Baseline*) |
| `--fail-on-new` | Exit code 1 on **regressions** only; requires `--baseline` |
| `--fail-on-violations` | Exit code 1 when a threshold is crossed, or a required QA tool is missing |
| `--config=FILE` | Configuration file (default: `phpx-complexity.json`) |
| `--top=N` | Number of rows in the ranking |
| `--exclude=FRAGMENT` | Excludes paths containing FRAGMENT (**repeatable**) |
| `--no-divergence` | Hides the divergence report |
| `--keep` | *(fetch)* Keeps the temporary copy of the repository |
| `-h`, `--help` | Help |

An unrecognised option **is refused** (exit code 2) rather than ignored: a typo
such as `--jsno` must not render a console report while letting CI believe it is
receiving JSON.

### Exit codes

| Code | Meaning |
|---:|---|
| `0` | Success |
| `1` | Violations, in gate mode (`--fail-on-violations` or `--fail-on-new`) |
| `2` | Usage or I/O error |

### Recipes

```bash
# A readable audit of a project
bin/phpx-complexity /path/to/project

# JSON output for CI
bin/phpx-complexity /path/to/project --json > complexity.json

# Standalone HTML report (a single file, opens on a double click)
bin/phpx-complexity /path/to/project --html=report.html

# QA tooling and tests of the audited project
bin/phpx-complexity /path/to/project --qa --coverage

# Strict gate: fails on any violation
bin/phpx-complexity /path/to/project --fail-on-violations

# Ratchet gate: fails on regressions only
bin/phpx-complexity /path/to/project --baseline=baseline.json --fail-on-new

# Audit a remote repository
bin/phpx-complexity fetch https://github.com/vendor/project.git --qa

# Narrow the scope
bin/phpx-complexity src/ --top=10 --exclude=/Legacy/ --exclude=/generated/
```

---

## Baseline and deltas

On an existing project, `--fail-on-violations` fails on the very first run:
hundreds of inherited violations nobody will fix at once, so the gate gets
disabled and stops serving any purpose. A baseline **accepts what exists** and
fails only on what **gets worse**, which is the model PHPStan and Psalm use.

This is the operational translation of the starting idea: since essential
entropy is irreducible, demanding zero complexity makes no sense on real code.
All that is demanded is that it **does not regress**.

```bash
# 1. Freeze the reference (once, committed to the repository)
bin/phpx-complexity src/ --baseline-out=baseline.json

# 2. Compare the current state
bin/phpx-complexity src/ --baseline=baseline.json

# 3. CI gate
bin/phpx-complexity src/ --baseline=baseline.json --fail-on-new
```

| Category | Meaning |
|---|---|
| **New violations** | crosses the threshold now, did not in the reference (new methods included) |
| **Worsened violations** | already above, and the value went up |
| **Resolved violations** | was above, no longer is |
| **Added / removed** | methods added or deleted, for context |

An **unchanged** inherited violation appears nowhere: only movement is shown.
`--fail-on-new` exits 1 on new and worsened ones only.

**Why `--baseline-out` rather than `--json`.** A snapshot is a subset of the
`--json` contract, and a full `--json` output remains a valid reference. But
that
output carries the percentile ranks and the divergence, which are **relative to
the batch**: they change for every method as soon as a single one moves.
Measured
on this repository by modifying one method:

| | lines changed | size |
|---|---:|---:|
| full `--json` output | 396 | 139 KB |
| `--baseline-out` | **4** | 69 KB |

A four-line diff gets reviewed; a four-hundred-line diff gets rubber-stamped,
and
a baseline nobody reads any more protects nothing.

Points of method:

- **The identity of a method is `file::method`**, never the line, which shifts on
  the slightest addition upstream. Namesakes within one file are separated by a
  rank. A rename shows up as removed plus added.
- **Classification uses the current thresholds**, the ones the gate has to
  enforce. A threshold that moved since the snapshot is reported separately.
- **`baseline.json` is committed and regenerated deliberately**, never
  automatically, without which the ratchet holds nothing back.

---

## QA tooling presence (`--qa`)

Audits the **quality tooling** of the target project: static analysis (PHPStan,
Psalm), standards (PHP-CS-Fixer, PHP_CodeSniffer), refactoring (Rector), tests
(PHPUnit, Pest, Behat, Infection), CI (GitHub Actions, GitLab CI), EditorConfig.

Each tool is detected through `composer.json` (require / require-dev) **or** the
presence of its configuration file. The project root is resolved by walking up
to
the `composer.json`.

This is a **fact of presence, not a judgement**. Tools declared under
`qa.required`
in the configuration and found missing make `--fail-on-violations` fail.

---

## Tests and coverage (`--coverage`)

Coverage is an **execution** measurement, and since the tool is static and
offline it does not compute it. `--coverage` therefore produces two distinct
blocks:

1. **Actual coverage**, read from a report already generated by the project
   (PHPUnit's Clover or Cobertura), auto-detected or pointed at by
   `coverage.path`. No report means **"not measured"**, never "0%", which would
   be an unfounded verdict.
2. **Test presence** (a static proxy): the number of test classes and methods,
   and the list of source classes with no `*Test` class at all. This is a
   **floor**, not coverage: the existence of a `FooTest` class does not prove
   that `Foo` is usefully tested.

---

## Auditing a remote repository (`fetch`)

```bash
bin/phpx-complexity fetch https://github.com/vendor/project.git
bin/phpx-complexity fetch git@github.com:vendor/project.git --qa --keep
```

Shallow-clones the repository into a temporary directory, audits it, then
**cleans up, including when the analysis fails**. `--keep` keeps the copy and
prints its path.

> **This is the only part of the tool that touches the network.** The analysis
> core knows nothing but a **local path**, so the offline guarantee of the
> analysis itself stays intact. The network is confined to an opt-in wrapper that
> no audit option can trigger.

**Accepted**: `https://`, `ssh://`, and the `git@host:path` form.
**Refused**: `git://` (neither encrypted nor authenticated), `file://` and local
paths (a local directory is analysed directly), the `ext::` transport (arbitrary
command execution) and anything starting with a dash, which git would take for
an
option. The command is passed as an array, so no shell and no interpolation,
with
`--` before the URL, hooks neutralised and a shallow clone.

**Private repositories**: authentication is git's own (SSH agent, credential
helper). Nothing is reinvented and **no prompt is ever raised**: an unreachable
repository fails immediately instead of hanging. A passphrase-protected key with
no agent loaded will therefore fail too.

**Limits**: `--coverage` needs a report that has **already been generated**, and a
clone alone does not bring one, so the module will only see static test
presence.
`--qa` works fully. The `git` binary is required, and its absence is reported.

---

## Configuration

Put a `phpx-complexity.json` at the root of the audited project (template:
`phpx-complexity.dist.json`):

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

| Key | Role |
|---|---|
| `thresholds` | Threshold per lens |
| `exclude` | Excluded path fragments |
| `top` | Size of the displayed ranking |
| `qa.required` | Tools whose absence fails the gate |
| `coverage.path` | Coverage report to read |
| `html.path` | Destination of the HTML report |

`exclude` fragments are compared to the path **relative to the audited root**,
never to the absolute path, so auditing a project installed under a system
directory is not emptied out by a fragment naming that directory.

---

## The quality of the project itself

The project holds itself to the requirements it audits:

```bash
scripts/qa.sh    # CS-Fixer (PSR-12) + PHPStan level 9 + PHPUnit + coverage >= 90%
composer qa      # the same
```

The same guard runs in **continuous integration** on PHP 8.2, 8.3 and 8.4
(`.github/workflows/ci.yml`): CS-Fixer, PHPStan, PHPUnit with the coverage
floor,
then the ratchet dogfooding. The badge at the top of this README reflects its
state on `main`.

Run the guard before every commit; a non-zero exit status means the commit needs
fixing. The script includes the tool **applied to its own code in ratchet
mode**:
inherited violations are frozen in `baseline.json` and pass, any regression
fails. Everything is offline.

Coverage is gated at 90% when a driver (Xdebug or PCOV) is available; with no
driver, the tests run without it rather than fail, because a partial guard beats
a bypassed one.

**`composer.lock` is versioned** and the two tools that can fail the gate are
constrained to the patch level. Without that, a minor PHPStan release bringing
new rules breaks CI without a line of code having moved. For a library this lock
binds development only: a package's lock file is ignored by its consumers.
Absorbing a version bump is therefore a **deliberate** act: `composer update`,
re-run `scripts/qa.sh`, deal with the new findings, commit the lock along with
them.

**Current state**: PHPStan level 9 clean, PSR-12 respected, 173 tests,
**95% coverage**, inherited violations frozen.

### Study tools

| Script | Role |
|---|---|
| `tools/corpus-study.sh` | Fetches a corpus of public PHP projects (cached) and freezes one audit per project |
| `tools/corpus-report.php` | Aggregates: distributions, rank correlations, marginal yield |
| `tools/live-peak-variant.php` | Replays the "parameters excluded" variant of `live_peak`, measured then discarded |
| `tools/coverage-gate.php` | Checks a clover report against a floor |
| `tools/build-phar.sh` | Builds the distributable PHAR |

---

## Building the PHAR

```bash
composer require --working-dir=var/box humbug/box:^4.7
tools/build-phar.sh
```

produces `phpx-complexity.phar`, a single distributable binary (about 320 KB).
The script lifts `phar.readonly` for the duration of the build and switches to
production dependencies only, otherwise PHPUnit and PHPStan would end up in the
archive, then restores the development environment whatever happens.

---

## Origin

The `params` (S107) and `returns` (S1142) lenses derive from custom PHPStan
rules
written for internal projects, reimplemented here with no PHPStan coupling. They
are also available as a PHPStan extension in
[phpstan-sonar-rules](https://github.com/LeclercqLaurent/phpstan-sonar-rules),
for a project that would rather plug them into an existing analysis than run a
separate auditor.

Released under the MIT license.
