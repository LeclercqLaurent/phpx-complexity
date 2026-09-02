<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * Sous-commande invoquée. « audit » est l'implicite : `phpx-complexity src/`
 * reste valide sans verbe.
 *
 * `fetch` est isolé volontairement — c'est la seule voie qui accède au réseau,
 * et elle ne doit pas pouvoir être déclenchée par une option enfouie dans le
 * pipeline d'analyse.
 */
enum Command: string
{
    case Audit = 'audit';
    case Fetch = 'fetch';
}
