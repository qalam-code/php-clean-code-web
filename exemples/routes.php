<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

return function (MoteurVue $vues): array {
    $saluer = function (Requete $requete, array $parametres) use ($vues): ReponseHtml {
        $nom = isset($parametres['nom'])
            ? $parametres['nom']
            : $requete->parametre('nom', 'visiteur');
        return new ReponseHtml(200, $vues->rendre('bonjour', ['nom' => $nom]));
    };

    return [
        [
            'chemin' => '/bonjour',
            'methodes' => ['GET'],
            'action' => $saluer,
        ],
        [
            'chemin' => '/bonjour/{nom}',
            'methodes' => ['GET'],
            'action' => $saluer,
        ],
    ];
};