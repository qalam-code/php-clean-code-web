<?php
declare(strict_types=1);

namespace App\Exemple\Application;

use App\Exemple\Domain\Contrat\DepotFactureInterface;
use App\Exemple\Domain\Entite\Facture;
use App\Exemple\Domain\ErreurFacturation;
use PhpCleanCode\Application\Port\AuthentificationInterface;
use PhpCleanCode\Domain\ErreurMetier;

/**
 * Cas d'usage : consulter une facture, en etant authentifie.
 *
 * L'ORDRE DES TROIS ETAPES N'EST PAS ARBITRAIRE :
 *   1. valider l'entree -- inutile d'authentifier pour une requete vide ;
 *   2. identifier l'appelant -- avant tout acces aux donnees ;
 *   3. lire.
 *
 * Inverser 2 et 3, c'est permettre a un appelant anonyme d'apprendre, au
 * choix des reponses, quelles factures existent.
 *
 * @package App\Exemple\Application
 */
final class ConsulterFacture
{
    private $authentification;
    private $factures;

    public function __construct(
        AuthentificationInterface $authentification,
        DepotFactureInterface $factures
    ) {
        $this->authentification = $authentification;
        $this->factures         = $factures;
    }

    /** @throws ErreurMetier PARAMETRE_MANQUANT, erreurs de jeton, FACTURE_INTROUVABLE. */
    public function executer(string $numero): Facture
    {
        if ($numero === '') {
            throw ErreurMetier::parametreManquant('numero');
        }

        // Leve si le jeton manque, est invalide, a expire, ou si le compte
        // n'existe plus. Le resultat n'est pas utilise ici, mais l'appel,
        // lui, est indispensable.
        $this->authentification->identifier();

        $facture = $this->factures->trouverParNumero($numero);
        if ($facture === null) {
            throw ErreurFacturation::factureIntrouvable($numero);
        }

        return $facture;
    }
}

