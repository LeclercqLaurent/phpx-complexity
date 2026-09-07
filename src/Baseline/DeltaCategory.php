<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * The nature of a delta between the reference snapshot and the current state.
 *
 * An inherited, unchanged violation falls into no category at all, which is the
 * whole point of a baseline: accept what exists so only movement shows.
 */
enum DeltaCategory: string
{
    case NewViolation = 'nouvelle';
    case Worsened = 'aggravee';
    case Resolved = 'resolue';
}
