<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpxComplexity\Config\Config;

/**
 * Liste des lentilles appliquées par défaut, seuils issus de la configuration.
 *
 * Ajouter une métrique = implémenter Lens puis l'enregistrer ici : c'est le seul
 * endroit à modifier.
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
