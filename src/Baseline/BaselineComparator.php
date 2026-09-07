<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

use PhpxComplexity\Config\Config;
use PhpxComplexity\Lens\Lens;

/**
 * Compares the current state against a reference snapshot.
 *
 * La classification s'appuie sur les seuils COURANTS, puisque c'est eux que le
 * gate mode has to enforce; a threshold that moved since the snapshot is
 * reported separately, so the reader knows the basis of comparison shifted.
 */
final class BaselineComparator
{
    /**
     * @param list<Lens> $lenses
     */
    public function __construct(
        private readonly array $lenses,
        private readonly Config $config,
    ) {
    }

    public function compare(Snapshot $baseline, Snapshot $current, string $source): Comparison
    {
        $deltas = [];
        foreach ($current->methods as $key => $method) {
            $before = $baseline->methods[$key] ?? null;
            foreach ($this->lenses as $lens) {
                $delta = $this->delta($method, $before, $lens);
                if (null !== $delta) {
                    $deltas[] = $delta;
                }
            }
        }

        return new Comparison(
            source: $source,
            deltas: $deltas,
            appeared: array_values(array_diff_key($current->methods, $baseline->methods)),
            disappeared: array_values(array_diff_key($baseline->methods, $current->methods)),
            thresholdChanges: $this->thresholdChanges($baseline),
        );
    }

    private function delta(MethodSnapshot $current, ?MethodSnapshot $before, Lens $lens): ?MethodDelta
    {
        $key = $lens->key();
        $after = $current->metric($key) ?? 0.0;
        $previous = $before?->metric($key);
        $category = $this->categorize($previous, $after, $this->config->threshold($key));

        return null === $category
            ? null
            : new MethodDelta($category, $current->file, $current->name, $key, $previous, $after);
    }

    private function categorize(?float $before, float $after, float $threshold): ?DeltaCategory
    {
        $isOver = $after > $threshold;
        if (null === $before) {
            // A method absent from the snapshot: it is born already in violation.
            return $isOver ? DeltaCategory::NewViolation : null;
        }

        $wasOver = $before > $threshold;

        return match (true) {
            $isOver && !$wasOver => DeltaCategory::NewViolation,
            $isOver && $after > $before => DeltaCategory::Worsened,
            $wasOver && !$isOver => DeltaCategory::Resolved,
            default => null,
        };
    }

    /**
     * @return array<string,array{from:float,to:float}>
     */
    private function thresholdChanges(Snapshot $baseline): array
    {
        $changes = [];
        foreach ($this->lenses as $lens) {
            $key = $lens->key();
            $from = $baseline->thresholds[$key] ?? null;
            $to = $this->config->threshold($key);
            if (null !== $from && $from !== $to) {
                $changes[$key] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }
}
