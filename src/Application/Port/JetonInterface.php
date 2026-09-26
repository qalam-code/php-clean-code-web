<?php
declare(strict_types=1);

namespace PhpCleanCode\Application\Port;

use PhpCleanCode\Domain\ErreurMetier;

/**
 * Emission et verification du jeton d'acces.
 *
 * Le domaine ne sait pas ce qu'est un JWT. Il sait qu'il existe un moyen de
 * transformer une identite en chaine, et cette chaine en identite. La
 * bibliotheque en fournit une implementation HS256 (Infrastructure\JetonJwt),
 * mais rien n'oblige a l'utiliser : jeton opaque en base, PASETO, session --
 * seul ce contrat est connu du reste du code.
 *
 * @package PhpCleanCode\Application\Port
 */
interface JetonInterface
{
    /**
     * @param array<string,mixed> $charge donnees a transporter ; doit contenir
     *                                    de quoi retrouver l'acteur.
     */
    public function emettre(array $charge, int $dureeSecondes): string;

    /**
     * @return array<string,mixed> la charge, si et seulement si le jeton est
     *                             intact, bien signe et non expire.
     * @throws ErreurMetier JETON_MALFORME, JETON_MAL_SIGNE ou JETON_EXPIRE.
     */
    public function verifier(string $jeton): array;
}


