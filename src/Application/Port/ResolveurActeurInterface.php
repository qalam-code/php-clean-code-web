<?php
declare(strict_types=1);

namespace PhpCleanCode\Application\Port;

use PhpCleanCode\Domain\Entite\Identite;

/**
 * Du contenu d'un jeton vers un utilisateur reel.
 *
 * Etape volontairement separee de la verification du jeton. Un jeton peut
 * etre parfaitement signe et non expire, et pourtant designer un compte
 * supprime ou desactive depuis son emission. Sans cette etape, le systeme
 * fait confiance a une photographie vieille de plusieurs heures.
 *
 * L'implementation naive -- prendre l'identifiant ecrit dans la charge et
 * s'en contenter -- fait disparaitre ce controle. Allez chercher
 * l'utilisateur.
 *
 * @package PhpCleanCode\Application\Port
 */
interface ResolveurActeurInterface
{
    /**
     * @param array<string,mixed> $charge charge utile du jeton, deja verifiee.
     * @return Identite|null null si personne ne correspond plus.
     */
    public function resoudre(array $charge): ?Identite;
}


