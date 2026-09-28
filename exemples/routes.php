<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

return function (MoteurVue $vues): array {
    $saluer = function (Requete $requete, array $parametres) use ($vues): ReponseHtml {
        $nom = isset($parametres['nom'])
            ? $parametres['nom']
            : $requete->parametre('nom', '');
        if (!is_string($nom)) {
            $nom = '';
        }
        return new ReponseHtml(200, $vues->rendre('bonjour', [
            'nom' => $nom,
            'nomAffiche' => $nom === '' ? 'visiteur' : $nom,
            'erreur' => null,
        ]));
    };

    $traiterFormulaire = function (Requete $requete) use ($vues): ReponseHtml {
        $nom = $requete->parametre('nom');
        $erreur = null;
        $code = 200;

        if (!is_string($nom)) {
            $nom = '';
            $erreur = 'Veuillez saisir un nom valide.';
        } else {
            $nom = trim($nom);
            $longueur = preg_match_all('/./us', $nom, $caracteres);
            if ($nom === '') {
                $erreur = 'Le nom est obligatoire.';
            } elseif ($longueur === false) {
                $erreur = 'Le nom contient un encodage invalide.';
            } elseif ($longueur > 100) {
                $erreur = 'Le nom ne doit pas depasser 100 caracteres.';
            }
        }

        if ($erreur !== null) {
            $code = 422;
        }
        return new ReponseHtml($code, $vues->rendre('bonjour', [
            'nom' => $nom,
            'nomAffiche' => $erreur === null ? $nom : '',
            'erreur' => $erreur,
        ]));
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
            'action' => $traiterFormulaire,
        ],
        [
            'chemin' => '/bonjour/{nom}',
            'methodes' => ['GET'],
            'action' => $saluer,
        ],
    ];
};