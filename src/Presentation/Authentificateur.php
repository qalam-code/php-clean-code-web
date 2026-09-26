<?php
declare(strict_types=1);

namespace PhpCleanCode\Presentation;

use PhpCleanCode\Application\Port\AuthentificationInterface;
use PhpCleanCode\Application\Port\JetonInterface;
use PhpCleanCode\Application\Port\ResolveurActeurInterface;
use PhpCleanCode\Domain\Entite\Identite;
use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Http\Requete;

/**
 * En-tete Authorization -> Identite.
 *
 * Trois etapes, dans cet ordre, et aucune n'est optionnelle :
 *   1. extraire le jeton de l'en-tete ;
 *   2. verifier signature et expiration ;
 *   3. retrouver l'utilisateur derriere la charge utile.
 *
 * L'etape 3 est celle qu'on supprime "parce que l'identifiant est deja dans
 * le jeton". C'est elle qui refuse un compte supprime ou desactive depuis
 * l'emission.
 *
 * @package PhpCleanCode\Presentation
 */
final class Authentificateur implements AuthentificationInterface
{
    private Requete $requete;
    private JetonInterface $jetons;
    private ResolveurActeurInterface $resolveur;
    private ?Identite $identite = null;

    public function __construct(
        Requete $requete,
        JetonInterface $jetons,
        ResolveurActeurInterface $resolveur
    ) {
        $this->requete   = $requete;
        $this->jetons    = $jetons;
        $this->resolveur = $resolveur;
    }

    public function identifier(): Identite
    {
        // Plusieurs cas d'usage peuvent demander l'identite dans la meme
        // requete ; le jeton n'est verifie qu'une fois.
        if ($this->identite !== null) {
            return $this->identite;
        }

        $entete = $this->requete->entete('Authorization');
        if ($entete === null || trim($entete) === '') {
            throw ErreurMetier::jetonAbsent();
        }

        $jeton = $this->extraire(trim($entete));
        if ($jeton === '') {
            throw ErreurMetier::jetonMalforme();
        }

        $charge   = $this->jetons->verifier($jeton);
        $identite = $this->resolveur->resoudre($charge);
        if ($identite === null) {
            throw ErreurMetier::acteurIntrouvable();
        }

        $this->identite = $identite;
        return $identite;
    }

    /** Accepte "Bearer xxx" comme "xxx" : les deux circulent. */
    private function extraire(string $entete): string
    {
        if (stripos($entete, 'bearer ') === 0) {
            return trim(substr($entete, 7));
        }
        return $entete;
    }
}

