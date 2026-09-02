<?php

declare(strict_types=1);

namespace PhpxComplexity\Baseline;

/**
 * Nature d'un écart entre l'instantané de référence et l'état courant.
 *
 * Une violation héritée et inchangée n'entre dans aucune catégorie : c'est tout
 * l'intérêt de la baseline, accepter l'existant pour n'exposer que le mouvement.
 */
enum DeltaCategory: string
{
    case NewViolation = 'nouvelle';
    case Worsened = 'aggravee';
    case Resolved = 'resolue';
}
