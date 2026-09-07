<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpxComplexity\Config\Config;

/**
 * The list of lenses applied by default, with thresholds taken from the config.
 *
 * Adding a metric means implementing Lens then registering it here: this is the
 * only place to change.
 */
final class LensRegistry
{
    /**
     * @return list<Lens>
     */
    public static function defaults(Config $config): array
    {
        return [
            new CognitiveComplexityLens($config->threshold('cognitive')),
            new ParameterCountLens($config->threshold('params')),
            new ReturnCountLens($config->threshold('returns')),
            new LiveVariablePeakLens($config->threshold('live_peak')),
            new EntanglementLens($config->threshold('entangle')),
        ];
    }
}
