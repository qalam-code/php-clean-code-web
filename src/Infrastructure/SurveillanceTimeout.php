<?php
declare(strict_types=1);

namespace PhpCleanCode\Infrastructure;

use PhpCleanCode\Application\Port\SurveillanceInterface;

/**
 * Garde-temps base sur l'horloge murale.
 *
 * LA MARGE EST LE POINT IMPORTANT. tempsRestant() ne repond pas "reste-t-il
 * du temps ?" mais "reste-t-il au moins une etape de temps ?". Une
 * surveillance qui laisse partir un appel sortant a une seconde de la limite
 * se fait couper au milieu, et c'est exactement la situation ou l'argent est
 * debite sans que la reponse soit enregistree.
 *
 * Reglez la marge sur la duree de l'etape la plus longue, pas sur zero.
 *
 * @package PhpCleanCode\Infrastructure
 */
final class SurveillanceTimeout implements SurveillanceInterface
{
    private float $depart;
    private float $limite;
    private float $marge;

    public function __construct(float $limiteSecondes = 25.0, float $margeSecondes = 5.0)
    {
        $this->depart = microtime(true);
        $this->limite = $limiteSecondes;
        $this->marge  = $margeSecondes;
    }

    public function tempsRestant(): bool
    {
        return $this->ecoule() < ($this->limite - $this->marge);
    }

    public function ecoule(): float
    {
        return microtime(true) - $this->depart;
    }
}

