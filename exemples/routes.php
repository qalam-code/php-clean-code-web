<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Http\GestionnaireCsrf;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Validation\ValidateurDonnees;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

return function (MoteurVue $vues, GestionnaireCsrf $csrf, ValidateurDonnees $validateur): array {
    $donneesSecurite = function () use ($csrf): array {
        return [
            'csrfToken' => $csrf->jeton(),
            'csrfField' => $csrf->cleFormulaire(),
        ];
    };

    $saluer = function (Requete $requete, array $parametres) use ($vues, $donneesSecurite): ReponseHtml {
        $nom = isset($parametres['nom'])
            ? $parametres['nom']
            : $requete->parametre('nom', '');
        if (!is_string($nom)) {
            $nom = '';
        }
        return new ReponseHtml(200, $vues->rendreAvecLayout('bonjour', 'layouts/principal', array_merge([
            'nom' => $nom,
            'nomAffiche' => $nom === '' ? 'visiteur' : $nom,
            'erreur' => null,
            'titre' => 'Bonjour',
        ], $donneesSecurite())));
    };

    $traiterFormulaire = function (Requete $requete, array $parametres) use ($vues, $donneesSecurite, $validateur): ReponseHtml {
        $resultat = $validateur->valider($requete->corps(), [
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
        $erreur = $resultat->premiereErreur();
        $code = $resultat->estValide() ? 200 : 422;
        return new ReponseHtml($code, $vues->rendreAvecLayout('bonjour', 'layouts/principal', array_merge([
            'nom' => $nom,
            'nomAffiche' => $resultat->estValide() ? $nom : '',
            'erreur' => $erreur,
            'titre' => 'Bonjour',
        ], $donneesSecurite())));
    };

    return [
        [
            'chemin' => '/bonjour',
            'methodes' => ['GET'],
            'action' => $saluer,
        ],
        [
            'chemin' => '/bonjour',
            'methodes' => ['POST'],
            'csrf' => true,
            'action' => $traiterFormulaire,
        ],
        [
            'chemin' => '/bonjour/{nom}',
            'methodes' => ['GET'],
            'action' => $saluer,
        ],
    ];
};
