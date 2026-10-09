<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Exemples\Controleur;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Application\MessagesFlash;
use QalamCode\PhpCleanCodeWeb\Http\GestionnaireCsrf;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseRedirection;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use QalamCode\PhpCleanCodeWeb\Validation\ValidateurDonnees;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

/** Contrôleur d'exemple : ses dépendances sont fournies par la composition de l'application. */
final class BonjourControleur
{
    private $vues;
    private $csrf;
    private $validateur;
    private $messagesFlash;
    private $genererUrl;

    public function __construct(
        MoteurVue $vues,
        GestionnaireCsrf $csrf,
        ValidateurDonnees $validateur,
        MessagesFlash $messagesFlash,
        callable $genererUrl
    ) {
        $this->vues = $vues;
        $this->csrf = $csrf;
        $this->validateur = $validateur;
        $this->messagesFlash = $messagesFlash;
        $this->genererUrl = $genererUrl;
    }

    /** Action GET : affiche la page Bonjour avec un nom fourni dans le chemin ou la query string. */
    public function saluer(Requete $requete, array $parametres): ReponseHtml
    {
        $nom = isset($parametres['nom'])
            ? $parametres['nom']
            : $requete->parametre('nom', '');
        if (!is_string($nom)) {
            $nom = '';
        }

        return new ReponseHtml(200, $this->vues->rendreAvecLayout('bonjour', 'layouts/principal', array_merge([
            'nom' => $nom,
            'nomAffiche' => $nom === '' ? 'visiteur' : $nom,
            'erreur' => null,
            'messageSucces' => $this->messagesFlash->consommer('success'),
            'titre' => 'Bonjour',
        ], $this->donneesSecurite())));
    }

    /** Action POST : valide le formulaire puis rend la page avec le statut approprié. */
    public function traiterFormulaire(Requete $requete, array $parametres): ReponseWeb
    {
        $resultat = $this->validateur->valider($requete->corps(), [
            'nom' => [
                'required' => true,
                'string' => true,
                'trim' => true,
                'max' => 100,
            ],
        ], [
            'nom' => 'Veuillez saisir un nom valide, de 1 a 100 caracteres.',
        ]);
        $nom = $resultat->donnees()['nom'];
        if (!is_string($nom)) {
            $nom = '';
        }

        if ($resultat->estValide()) {
            $this->messagesFlash->ajouter('success', 'Le formulaire a bien ete envoye.');
            // Le nom de route évite de recopier le chemin de la page d'accueil.
            $genererUrl = $this->genererUrl;
            return new ReponseRedirection($genererUrl('bonjour.index'));
        }

        return new ReponseHtml(422, $this->vues->rendreAvecLayout('bonjour', 'layouts/principal', array_merge([
            'nom' => $nom,
            'nomAffiche' => '',
            'erreur' => $resultat->premiereErreur(),
            'messageSucces' => null,
            'titre' => 'Bonjour',
        ], $this->donneesSecurite())));
    }

    /** Données de sécurité partagées par les rendus des actions du contrôleur. */
    private function donneesSecurite(): array
    {
        return [
            'csrfToken' => $this->csrf->jeton(),
            'csrfField' => $this->csrf->cleFormulaire(),
        ];
    }
}
