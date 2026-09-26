<?php
declare(strict_types=1);

namespace App\Exemple\Presentation\Controleur;

use App\Exemple\Application\Connecter;
use App\Exemple\Presentation\Presentateur\PresentateurConnexion;
use PhpCleanCode\Http\Requete;
use PhpCleanCode\Presentation\ReponseHttp;

/**
 * Adaptateur : de la requete HTTP vers le cas d'usage, et retour.
 *
 * UN CONTROLEUR NE DECIDE RIEN. Il extrait, il appelle, il presente. Trois
 * lignes utiles, et c'est normal : la regle metier est dans le cas d'usage,
 * le format de reponse dans le presentateur.
 *
 * Il ne contient aucun "try" : les ErreurMetier remontent jusqu'a
 * Aiguillage, qui les confie au presentateur de la route. Attraper ici
 * dupliquerait ce mecanisme, avec le risque de le faire differemment d'un
 * endpoint a l'autre.
 *
 * @package App\Exemple\Presentation\Controleur
 */
final class ControleurConnexion
{
    private $connecter;
    private $presentateur;

    public function __construct(Connecter $connecter, PresentateurConnexion $presentateur)
    {
        $this->connecter    = $connecter;
        $this->presentateur = $presentateur;
    }

    public function __invoke(Requete $requete): ReponseHttp
    {
        $resultat = $this->connecter->executer(
            (string) $requete->parametre('identifiant', ''),
            (string) $requete->parametre('mot_de_passe', '')
        );

        return $this->presentateur->jetonEmis($resultat['jeton'], $resultat['expire_dans']);
    }
}

