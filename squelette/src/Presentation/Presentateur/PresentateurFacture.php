<?php
declare(strict_types=1);

namespace App\Exemple\Presentation\Presentateur;

use App\Exemple\Domain\Entite\Facture;
use App\Exemple\Domain\ErreurFacturation;
use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Presentation\Presentateur\PresentateurCommun;
use PhpCleanCode\Presentation\ReponseHttp;

/**
 * Contrat HTTP de /facture.
 *
 * Ce fichier repond a lui seul a la question "que rend cet endpoint, dans
 * tous les cas ?". C'est le but : le contrat tient sur un ecran, se relit
 * avant une mise en production, et se teste sans base ni serveur.
 *
 * @package App\Exemple\Presentation\Presentateur
 */
final class PresentateurFacture extends PresentateurCommun
{
    public function facture(Facture $facture): ReponseHttp
    {
        // L'ORDRE DES CLES CI-DESSOUS EST L'ORDRE DU JSON RENDU, et il fait
        // partie du contrat des que des tiers consomment l'API.
        return $this->reponse(200, [
            'statut'        => 'succes',
            'numero'        => $facture->numero(),
            'montant'       => $facture->montantAffiche(),
            'etat'          => $facture->statut(),
            'date_emission' => $facture->dateEmission(),
        ]);
    }

    public function traduire(ErreurMetier $erreur): ReponseHttp
    {
        switch ($erreur->type()) {
            case ErreurFacturation::FACTURE_INTROUVABLE:
                // Le numero demande n'est PAS repete dans le message : il
                // vient de l'appelant, et le renvoyer tel quel expose au
                // moindre client qui l'afficherait sans echapper.
                return $this->echec(404, 'erreur', 'facture introuvable');

            case ErreurFacturation::FACTURE_DEJA_PAYEE:
                return $this->echec(409, 'erreur', 'facture deja payee');

            default:
                return parent::traduire($erreur);
        }
    }
}

