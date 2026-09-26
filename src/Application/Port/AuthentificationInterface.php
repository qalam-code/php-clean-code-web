<?php
declare(strict_types=1);

namespace PhpCleanCode\Application\Port;

use PhpCleanCode\Domain\Entite\Identite;
use PhpCleanCode\Domain\ErreurMetier;

/**
 * Qui appelle ?
 *
 * Un cas d'usage qui a besoin de savoir a qui imputer une action depend de
 * cette interface, et de rien d'autre. Il ignore s'il y a un jeton, un
 * en-tete, un cookie ou une session derriere.
 *
 * IMPLEMENTEZ-LA PARESSEUSEMENT. La racine de composition cable
 * l'authentificateur dans tous les cas d'usage ; si la construction ouvre la
 * connexion a la base, une base injoignable fait echouer l'endpoint avant
 * qu'il ait pu repondre "jeton absent" a une requete qui n'en portait pas.
 * Le diagnostic rendu au consommateur devient faux.
 *
 * @package PhpCleanCode\Application\Port
 */
interface AuthentificationInterface
{
    /**
     * @throws ErreurMetier JETON_ABSENT, JETON_MALFORME, JETON_MAL_SIGNE,
     *                      JETON_EXPIRE ou ACTEUR_INTROUVABLE.
     */
    public function identifier(): Identite;
}


