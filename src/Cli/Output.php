<?php

declare(strict_types=1);

namespace PhpxComplexity\Cli;

/**
 * Destination des sorties du CLI.
 *
 * Exister en tant que collaborateur plutôt qu'en appels directs à STDOUT permet
 * de vérifier le comportement de l'application EN MÉMOIRE : sans cette couture,
 * la seule façon de la tester est de lancer un sous-processus, que les outils de
 * couverture n'instrumentent pas.
 */
interface Output
{
    public function write(string $text): void;

    /**
     * Message d'erreur ; le saut de ligne final est ajouté.
     */
    public function error(string $line): void;
}
