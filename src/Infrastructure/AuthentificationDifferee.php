<?php
declare(strict_types=1);

namespace PhpCleanCode\Infrastructure;

use PhpCleanCode\Application\Port\AuthentificationInterface;
use PhpCleanCode\Domain\Entite\Identite;

/**
 * Enveloppe qui retarde la construction du vrai authentificateur.
 *
 * NE PAS LA PRENDRE POUR UNE ELEGANCE GRATUITE. Elle repare un defaut precis.
 *
 * La racine de composition cable l'authentificateur dans chaque cas d'usage.
 * Si le construire ouvre la connexion a la base -- parce qu'il lui faut un
 * depot pour retrouver l'acteur -- alors une base injoignable fait echouer
 * l'endpoint AVANT qu'il ait pu examiner la requete. Une requete sans jeton
 * recoit alors "erreur interne" au lieu de "token introuvable" : le
 * diagnostic rendu au consommateur devient faux, et l'anomalie ne se voit
 * que le jour ou la base tombe.
 *
 * Avec cette enveloppe, rien n'est construit tant que identifier() n'est pas
 * appele -- c'est-a-dire tant qu'un cas d'usage n'a pas vraiment besoin de
 * savoir qui appelle.
 *
 * @package PhpCleanCode\Infrastructure
 */
final class AuthentificationDifferee implements AuthentificationInterface
{
    /** @var callable(): AuthentificationInterface */
    private $construire;
    private ?AuthentificationInterface $reel = null;

    /** @param callable(): AuthentificationInterface $construire */
    public function __construct(callable $construire)
    {
        $this->construire = $construire;
    }

    public function identifier(): Identite
    {
        if ($this->reel === null) {
            $fabrique    = $this->construire;
            $this->reel  = $fabrique();
        }
        return $this->reel->identifier();
    }
}

