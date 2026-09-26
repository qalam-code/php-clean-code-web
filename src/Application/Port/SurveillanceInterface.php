<?php
declare(strict_types=1);

namespace PhpCleanCode\Application\Port;

/**
 * Garde-temps d'un traitement.
 *
 * Utile des qu'un cas d'usage appelle un service tiers ou boucle sur un
 * ensemble de taille inconnue : il consulte la surveillance entre deux
 * etapes et s'arrete proprement au lieu de se faire tuer par
 * max_execution_time, au milieu d'une ecriture.
 *
 * @package PhpCleanCode\Application\Port
 */
interface SurveillanceInterface
{
    /** @return bool true s'il reste du temps pour une etape de plus. */
    public function tempsRestant(): bool;

    /** @return float secondes ecoulees depuis le debut du traitement. */
    public function ecoule(): float;
}


