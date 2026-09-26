<?php
declare(strict_types=1);

namespace App\Exemple\Presentation\Presentateur;

use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Presentation\Presentateur\PresentateurCommun;
use PhpCleanCode\Presentation\ReponseHttp;

/**
 * Contrat HTTP de /login.
 *
 * @package App\Exemple\Presentation\Presentateur
 */
final class PresentateurConnexion extends PresentateurCommun
{
    public function jetonEmis(string $jeton, int $dureeSecondes): ReponseHttp
    {
        return $this->reponse(200, [
            'statut'      => 'succes',
            'token'       => $jeton,
            'expire_dans' => $dureeSecondes,
        ]);
    }

    public function traduire(ErreurMetier $erreur): ReponseHttp
    {
        switch ($erreur->type()) {
            case ErreurMetier::IDENTIFIANTS_INVALIDES:
                // Message volontairement vague, et identique que le compte
                // soit inconnu ou le mot de passe faux.
                return $this->echec(403, 'erreur', 'identifiants invalides');

            default:
                // Les types communs -- parametre manquant, panne -- sont
                // deja traduits par la bibliotheque. Ne les recopiez pas.
                return parent::traduire($erreur);
        }
    }
}

