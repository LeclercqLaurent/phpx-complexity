<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * The invoked subcommand. "audit" is the implicit one: `phpx-complexity src/`
 * reste valide sans verbe.
 *
 * `fetch` is isolated on purpose: it is the only route that touches the network,
 * and it must not be triggerable by an option buried in the
 * pipeline d'analyse.
 */
enum Command: string
{
    case Audit = 'audit';
    case Fetch = 'fetch';
}
