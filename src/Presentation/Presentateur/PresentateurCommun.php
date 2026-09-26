<?php
declare(strict_types=1);

namespace PhpCleanCode\Presentation\Presentateur;

use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Presentation\ReponseHttp;

/**
 * Traductions des types definis par la bibliotheque.
 *
 * Tout endpoint authentifie rencontre les memes six erreurs de jeton. Les
 * redecrire dans chaque presentateur, c'est six occasions de repondre 401 ici
 * et 403 la pour la meme cause.
 *
 * Votre presentateur en herite, traite ses propres types, puis delegue :
 *
 *     public function traduire(ErreurMetier $erreur): ReponseHttp
 *     {
 *         switch ($erreur->type()) {
 *             case ErreurFacturation::FACTURE_DEJA_PAYEE:
 *                 return $this->echec(409, 'erreur', 'facture deja payee');
 *             default:
 *                 return parent::traduire($erreur);
 *         }
 *     }
 *
 * Les codes ci-dessous sont un point de depart, pas une norme : redefinissez
 * la methode si votre API existante repond autrement. LE FORMAT EXISTANT
 * PRIME.
 *
 * @package PhpCleanCode\Presentation\Presentateur
 */
class PresentateurCommun extends PresentateurAbstrait
{
    public function traduire(ErreurMetier $erreur): ReponseHttp
    {
        switch ($erreur->type()) {
            case ErreurMetier::PARAMETRE_MANQUANT:
                // Le detail nomme les parametres absents : information utile
                // a l'appelant, et qui ne revele rien du systeme.
                return $this->echec(400, 'erreur', $erreur->getMessage());

            case ErreurMetier::RESSOURCE_INTROUVABLE:
                return $this->echec(404, 'erreur', 'ressource introuvable');

            case ErreurMetier::JETON_ABSENT:
                return $this->echec(400, 'erreur', 'token introuvable');

            case ErreurMetier::JETON_MALFORME:
            case ErreurMetier::JETON_MAL_SIGNE:
                // MEME REPONSE POUR LES DEUX, DELIBEREMENT. Distinguer
                // "illisible" de "mal signe" indique a qui forge des jetons
                // lequel de ses essais approche du but.
                return $this->echec(401, 'erreur', 'token invalide');

            case ErreurMetier::JETON_EXPIRE:
                // Celle-ci se distingue : le client legitime doit savoir
                // qu'il lui suffit de se reconnecter.
                return $this->echec(401, 'erreur', 'token expire');

            case ErreurMetier::ACTEUR_INTROUVABLE:
                return $this->echec(401, 'erreur', 'token invalide');

            case ErreurMetier::IDENTIFIANTS_INVALIDES:
                return $this->echec(403, 'erreur', 'identifiants invalides');

            case ErreurMetier::ECHEC_ECRITURE:
            case ErreurMetier::ECHEC_JOURNALISATION:
                // getMessage() contient le detail technique. Il va au
                // journal, jamais dans le corps de la reponse.
                return $this->panne();

            default:
                return $this->panne();
        }
    }
}

