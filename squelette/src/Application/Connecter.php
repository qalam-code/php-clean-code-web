<?php
declare(strict_types=1);

namespace App\Exemple\Application;

use App\Exemple\Domain\Contrat\DepotUtilisateurInterface;
use PhpCleanCode\Application\Port\JetonInterface;
use PhpCleanCode\Application\Port\JournalInterface;
use PhpCleanCode\Domain\ErreurMetier;

/**
 * Cas d'usage : echanger des identifiants contre un jeton.
 *
 * ANATOMIE D'UN CAS D'USAGE -- le modele vaut pour tous les autres :
 *   - il recoit des interfaces, jamais des objets concrets ;
 *   - il ne connait ni HTTP, ni SQL, ni JSON ;
 *   - il rend une donnee ou leve une ErreurMetier, et rien d'autre ;
 *   - il tient en un ecran. Au-dela, il fait le travail de deux.
 *
 * Aucun "echo", aucun "header", aucun code HTTP : c'est ce qui permet de
 * l'eprouver entierement en memoire, avec des doubles.
 *
 * @package App\Exemple\Application
 */
final class Connecter
{
    private DepotUtilisateurInterface $utilisateurs;
    private JetonInterface $jetons;
    private JournalInterface $journal;
    private int $dureeJeton;

    public function __construct(
        DepotUtilisateurInterface $utilisateurs,
        JetonInterface $jetons,
        JournalInterface $journal,
        int $dureeJeton = 3600
    ) {
        $this->utilisateurs = $utilisateurs;
        $this->jetons       = $jetons;
        $this->journal      = $journal;
        $this->dureeJeton   = $dureeJeton;
    }

    /**
     * @return array{jeton:string,expire_dans:int}
     * @throws ErreurMetier PARAMETRE_MANQUANT ou IDENTIFIANTS_INVALIDES.
     */
    public function executer(string $identifiant, string $motDePasse): array
    {
        if ($identifiant === '' || $motDePasse === '') {
            throw ErreurMetier::parametreManquant('identifiant, mot_de_passe');
        }

        $identite = $this->utilisateurs->parIdentifiants($identifiant, $motDePasse);
        if ($identite === null) {
            // UNE SEULE ERREUR POUR LES DEUX CAS. Distinguer "compte inconnu"
            // de "mot de passe faux" permet d'enumerer les comptes existants.
            throw ErreurMetier::identifiantsInvalides();
        }

        // LE JETON NE TRANSPORTE QU'UN IDENTIFIANT. La charge est lisible par
        // tous ; le nom, le role ou l'adresse n'ont rien a y faire, et seront
        // de toute facon relus en base a chaque requete.
        $jeton = $this->jetons->emettre(['sub' => $identite->id()], $this->dureeJeton);

        // Le journal rend false plutot que de lever (voir JournalInterface).
        // ICI, ON CHOISIT DE POURSUIVRE : refuser une connexion valide parce
        // que sa trace n'a pas pu s'ecrire serait pire que la trace perdue.
        // Ce choix doit etre ECRIT, sinon personne ne saura qu'il a ete fait.
        $this->journal->enregistrer($identite->id(), 'connexion');

        return ['jeton' => $jeton, 'expire_dans' => $this->dureeJeton];
    }
}

