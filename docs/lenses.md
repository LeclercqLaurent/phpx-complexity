# Precise definition of the in-house lenses

`cognitive`, `params` and `returns` reproduce published SonarQube rules, and
their specification lives with those rules. `live_peak` and `entangle` have no
external specification, so this document *is* their specification. A metric that
cannot be explained precisely cannot serve as an engineering constraint.

Everything below describes what the code actually does, and every claim here is
covered by fixtures under `tests/`.

---

## Shared conventions

Both lenses operate on the **body statements** of the analysed function, walking
the php-parser syntax tree.

**`$this` is never a variable.** It is excluded by name, so `$this->total` reads
as a property access and contributes nothing.

**Variable variables are excluded.** `$$name` has a non-string node name and is
skipped rather than guessed at.

**Nested functions are not entered.** A closure, an arrow function, or a function
or method declared inside the body is a boundary: its variables belong to it, not
to the enclosing function. The enclosing function is charged for holding a
closure in its flow, never for what the closure does inside.

**Parameters count only where they appear in the body.** The signature is not
read; a parameter that is never used contributes nothing to either lens. This is
why `live_peak` correlates with S107 without duplicating it: it measures the
arguments a reader actually has to carry, not the ones declared.

---

## `live_peak`, the peak of simultaneously live variables

**What it measures.** The largest number of local variables whose lifetimes
overlap on a single line. A proxy for the working memory a reader must hold, in
the sense of the 7 ± 2 figure.

**Lifetime.** For each local variable, the interval from the line of its first
occurrence to the line of its last, inclusive. There is no liveness analysis in
the compiler sense: a variable assigned on line 3 and read again on line 40 is
considered live for the 38 lines in between, even if nothing touches it there,
because the reader has to keep it in mind across that distance.

**Computation.** Every interval contributes +1 at its first line and -1 just
after its last. Sweeping the lines in order and keeping the running maximum gives
the peak. A body with no local variable yields 0.

**Worked example.**

```php
public function example(int $a, int $b): int   // signature is not read
{
    $x = $a + $b;        // line 3: $a, $b, $x become live
    $y = $x * 2;         // line 4: $x last used, $y becomes live
    return $y;           // line 5: $y last used
}
```

| Variable | Lifetime | |
|---|---|---|
| `$a` | lines 3 to 3 | |
| `$b` | lines 3 to 3 | |
| `$x` | lines 3 to 4 | |
| `$y` | lines 4 to 5 | |

Line 3 carries `$a`, `$b` and `$x`, so `live_peak` is **3**.

**The consequence to be aware of.** Lifetimes are measured in source lines, so
the metric is sensitive to formatting. Spreading one expression over three lines
lengthens the intervals it touches. This is deliberate rather than accidental:
the reading distance is the thing being approximated. It does mean a comparison
between two codebases with different formatting conventions is not meaningful,
which is one more reason the tool ranks within a codebase and never across.

**Default threshold: 8.** Measured at roughly the 98.7th percentile over 40,594
methods from public projects, which puts it in the same range as the other
lenses. See `docs/lens-validation.md`.

---

## `entangle`, the average degree of the co-occurrence graph

**What it measures.** How much the local variables of a function depend on one
another. It is the only axis covered by neither S3776, which counts branches, nor
S107, which counts the signature.

**The graph.** Vertices are the local variables appearing anywhere in the body,
isolated ones included. Edges are undirected and deduplicated: two variables
linked twice count once.

**What creates an edge**, and nothing else does:

| Construct | Edges created |
|---|---|
| Assignment, including compound and by reference | every variable of the target with every variable of the right-hand side |
| Binary operator | every variable on the left with every variable on the right |
| Ternary | every variable of the condition with every variable of either branch |

**What does not create an edge**, and this is the point of the lens:

- Two arguments of the same call. `f($a, $b)` does not link `$a` to `$b`: passing
  two values side by side does not combine them.
- A flat assignment from a property or to one. `$this->x = $x` creates the vertex
  `$x` and no edge at all, which is what separates the flat entropy of a factory
  assembling eight fields from the interwoven entropy of a method whose variables
  genuinely refer to one another.
- A variable that is only ever read alone.

**Computation.** The average degree of an undirected graph, `2 x edges /
vertices`. A body with fewer than two variables yields 0.

**Worked example.**

```php
$c = $a + $b;
```

The binary operator links `$a` to `$b`. The assignment links `$c` to both `$a`
and `$b`. Three vertices, three edges, so the average degree is
`2 x 3 / 3` = **2.0**.

Compare with a factory:

```php
$this->name  = $name;
$this->email = $email;
$this->city  = $city;
```

Three vertices, no edge, average degree **0**, and a `live_peak` of 1. Both
bodies hold three variables, yet only one of them ever asks the reader to hold
three at once.

Both figures come from running the tool on those exact snippets, not from reading
the algorithm.

**Why isolated variables stay in the denominator.** Counting only the variables
that carry an edge would inflate the average of any function with a dense core
and a long tail of independent locals. Keeping them means the metric answers
"how interwoven is this function", not "how interwoven is its most interwoven
part".

**Default threshold: 3.** It started at 4, which sat beyond the 99.8th percentile
and made the lens almost inert: 85 methods flagged out of 40,594. Lowered to 3 on
the evidence, it flags 415, of which 163 are invisible to S3776. The measurement
was right and the threshold was wrong, which is documented in
`docs/lens-validation.md`.

---

## Reading the two together

Neither lens is meant to be read alone.

| `live_peak` | `entangle` | Reading |
|---|---|---|
| high | low | Usually benign: assembly, factories, wide but flat signatures. Check once, then move on. |
| high | high | State that is both plentiful and interdependent. This is the configuration worth a human look. |
| low | high | Few variables, tightly coupled. Often a condensed expression that would read better unpacked. |
| low | low | Nothing to say. |

`live_peak` on its own does not distinguish a factory with eight fields from a
method that genuinely interweaves eight variables. `entangle` is what separates
them, and it is the reason the pair exists rather than either metric alone.
