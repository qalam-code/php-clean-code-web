<?php
declare(strict_types=1);

namespace PhpCleanCode\Test;

use PhpCleanCode\Application\Port\SurveillanceInterface;

/**
 * Garde-temps pilote a la main.
 *
 * Le temps est la dependance qu'on ne peut pas attendre en test : une boucle
 * de traitement qui doit s'arreter au bout de vingt-cinq secondes ne se teste
 * pas en patientant vingt-cinq secondes. On decide ici quand le temps manque.
 *
 * epuiserApres(2) : deux etapes passent, la troisieme est refusee. C'est le
 * scenario qui compte, celui ou l'arret survient AU MILIEU du traitement.
 *
 * @package PhpCleanCode\Test
 */
final class SurveillanceFactice implements SurveillanceInterface
{
    private int $restantes;
    private float $ecoule;
    public int $consultations = 0;

    /** @param int $etapes nombre d'appels a tempsRestant() qui repondront true. */
    public function __construct(int $etapes = PHP_INT_MAX, float $ecoule = 0.0)
    {
        $this->restantes = $etapes;
        $this->ecoule    = $ecoule;
    }

    public static function epuiserApres(int $etapes): self
    {
        return new self($etapes);
    }

    public function tempsRestant(): bool
    {
        $this->consultations++;
        if ($this->restantes <= 0) {
            return false;
        }
        $this->restantes--;
        return true;
    }

    public function ecoule(): float
    {
        return $this->ecoule;
    }
}

