# phpx-complexity

A **multi-lens**, **offline** PHP complexity auditor with a single dependency
(`nikic/php-parser`). It natively reproduces the SonarQube "complexity" family of
rules, adds two lenses of its own, then **plays the lenses against each other** to
reveal what any isolated metric lets through.

[![CI](https://github.com/LeclercqLaurent/phpx-complexity/actions/workflows/ci.yml/badge.svg)](https://github.com/LeclercqLaurent/phpx-complexity/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.2-777BB4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![Coverage](https://img.shields.io/badge/coverage-95%25-brightgreen)](#the-quality-of-the-project-itself)

PHP >= 8.2 · MIT · `codeam/phpx-complexity` · single dependency: `nikic/php-parser`

---

## The starting idea: QA as an entropy policy

The tool grew out of one underlying argument: **QA rules are proxies for
entropy** in Shannon's sense, the number of bits needed to describe the behaviour
of a method. S3776 bounds execution paths, S107 the degrees of freedom on input,
S1142 the terminal branches. Each puts a limit on one facet of "how much
information you have to hold in mind to understand this code".

What remains is the causal link one assumes between QA and entropy. The naive
answer, *QA lowers entropy*, does not hold: if that were the goal, the best code
would always be the most trivial, and software that does nothing would be a
masterpiece. The position taken here is narrower:

> **Essential** entropy is irreducible: it comes from the problem to be solved,
> not from the way it is written (Brooks, *No Silver Bullet*). QA eliminates
> **accidental** entropy, the kind added without necessity, which is what DRY,
> KISS and YAGNI target, and it **localises** essential entropy into packets that
> fit under the reader's cognitive threshold.
>
> It is a policy of **compression and localisation**, not a fight against
> complexity.

Three direct consequences, which explain the tool far better than its options do.

**The existing rules are missing an axis.** S3776 counts *branches* but is blind
to **dependency between symbols**. Two methods with a cognitive complexity of 12
can impose very different reading loads depending on whether their variables are
independent or interwoven. That gap is what the two in-house lenses, `live_peak`
and `entangle`, fill.

**Zero complexity is not the requirement; not growing without reason is.** Since
essential entropy is irreducible, an absolute threshold is unenforceable on
existing code. Hence the **ratchet** mode: accept what exists, fail only on
regressions.

**A score would be a contradiction in terms.** The entropy of a method is not a
grade; comparing it to a threshold is a fact, summarising it as 7.5/10 is an
opinion in disguise. Hence the firm refusal to produce any score at all.

---

## Why cross the lenses

An isolated complexity metric has blind spots. Cognitive complexity (S3776)
counts branches but ignores a method with no `if` that interweaves ten variables;
the parameter count (S107) ignores internal logic.

Crossing several lenses surfaces the methods where they **contradict each other**,
and that is where the problems a single-metric linter lets through are hiding. On
clean code the lenses converge. **Their divergence is the signal.**

This is not a hunch: measured over 40,594 methods from 10 public PHP projects,
the rank correlations between lenses range from -0.02 to 0.72. They really do
rank differently. Details in
[docs/lens-validation.md](docs/lens-validation.md).

### Guiding principle: factual, never a score

In practice: **no score, neither per item nor overall**, anywhere, not in the
console, not in the JSON, not in the HTML. Only **counters and raw values**
compared to thresholds. Tests verify this on every output format.

---

## The 5 lenses

| Key | Ref. | Measures | Threshold |
|---|---|---|---:|
| `cognitive` | S3776 | Cognitive complexity: branching + nesting + sequences of logical operators | 15 |
| `params` | S107 | Number of parameters in the signature | 7 |
| `returns` | S1142 | Number of `return` statements | 3 |
| `live_peak` | | Peak of simultaneously live variables (working memory, 7±2) | 8 |
| `entangle` | | Entanglement: average degree of the variable co-occurrence graph | 3 |

**`cognitive`, `params` and `returns` are native reimplementations** (pure
php-parser, no PHPStan). Their conformance to the published rules is **tested case
by case**, with expected values derived from the specification rather than from
our own output, which is the only way for a test to catch a faulty
reimplementation (`tests/Lens/*ConformanceTest.php`).

Accepted and documented deviations: recursion is not counted (it would require
interprocedural analysis), `match` is treated as a `switch` (it postdates the
spec), and `else { if ... }` counts as an `else if`, because php-parser produces
the same tree for both and PHP itself does not distinguish `else if` from
`elseif`.

**`live_peak` and `entangle` belong to the tool.** They are read **against each
other**: a high peak with no entanglement (a factory with 8 fields) is not a
problem; a high peak *with* strong entanglement is.

A measured and accepted reservation: `live_peak` counts the parameters **used** in
the body, because for the reader an argument that has to be kept in mind does
occupy working memory. The lens is therefore not orthogonal to S107 (correlation
0.70, higher than its 0.64 with S3776).

### The divergence report

The heart of the tool. It compares the **percentile ranks** of methods under each
lens and surfaces those where the lenses contradict one another: low on S3776 but
high on liveness, for instance. Hidden with `--no-divergence`.

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

PHARs are published on the [releases page](https://github.com/LeclercqLaurent/phpx-complexity/releases)
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

The verb is optional: `audit` is implicit, `fetch` is the only explicit one. With
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
`--json` contract, and a full `--json` output remains a valid reference. But that
output carries the percentile ranks and the divergence, which are **relative to
the batch**: they change for every method as soon as a single one moves. Measured
on this repository by modifying one method:

| | lines changed | size |
|---|---:|---:|
| full `--json` output | 396 | 139 KB |
| `--baseline-out` | **4** | 69 KB |

A four-line diff gets reviewed; a four-hundred-line diff gets rubber-stamped, and
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
presence of its configuration file. The project root is resolved by walking up to
the `composer.json`.

This is a **fact of presence, not a judgement**. Tools declared under `qa.required`
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
command execution) and anything starting with a dash, which git would take for an
option. The command is passed as an array, so no shell and no interpolation, with
`--` before the URL, hooks neutralised and a shallow clone.

**Private repositories**: authentication is git's own (SSH agent, credential
helper). Nothing is reinvented and **no prompt is ever raised**: an unreachable
repository fails immediately instead of hanging. A passphrase-protected key with
no agent loaded will therefore fail too.

**Limits**: `--coverage` needs a report that has **already been generated**, and a
clone alone does not bring one, so the module will only see static test presence.
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
(`.github/workflows/ci.yml`): CS-Fixer, PHPStan, PHPUnit with the coverage floor,
then the ratchet dogfooding. The badge at the top of this README reflects its
state on `main`.

Run the guard before every commit; a non-zero exit status means the commit needs
fixing. The script includes the tool **applied to its own code in ratchet mode**:
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

The `params` (S107) and `returns` (S1142) lenses derive from custom PHPStan rules
written for internal projects, reimplemented here with no PHPStan coupling. They
are also available as a PHPStan extension in
[phpstan-sonar-rules](https://github.com/LeclercqLaurent/phpstan-sonar-rules),
for a project that would rather plug them into an existing analysis than run a
separate auditor.

Released under the MIT license.
