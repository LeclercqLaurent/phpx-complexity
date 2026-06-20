<?php

declare(strict_types=1);

namespace PhpxComplexity\Lens;

use PhpParser\Node;

/**
 * Une « lentille » mesure une facette de complexité d'une méthode/fonction.
 * Chaque lentille produit une valeur brute comparable à un seuil ; le rapport de
 * divergence confronte les rangs de toutes les lentilles pour repérer les
 * méthodes où elles se contredisent (l'angle mort des métriques isolées).
 */
interface Lens
{
    /** Clé courte et stable (ex. « cognitive », « params »). */
    public function key(): string;

    /** Libellé lisible. */
    public function label(): string;

    /** Référence SonarQube si applicable (ex. « S3776 »), sinon chaîne vide. */
    public function reference(): string;

    /**
     * Valeur brute pour une fonction/méthode.
     *
     * @param Node\Stmt[] $stmts corps de la fonction (jamais null)
     */
    public function measure(Node\FunctionLike $function, array $stmts): float;

    /** Seuil par défaut au-delà duquel la valeur est considérée élevée. */
    public function defaultThreshold(): float;
}
