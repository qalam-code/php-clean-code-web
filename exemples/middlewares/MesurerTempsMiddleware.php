<?php
declare(strict_types=1);

namespace QalamCode\PhpCleanCodeWeb\Exemples\Middleware;

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;

/**
 * Middleware d'exemple invokable : mesure l'action suivante et ajoute un en-tête.
 * Il n'a pas de dépendance ici, mais l'application peut lui en injecter au constructeur.
 */
final class MesurerTempsMiddleware
{
    public function __invoke(Requete $requete, array $parametres, callable $suite): ReponseWeb
    {
        $debut = microtime(true);
        $reponse = $suite();
        $entetes = $reponse->entetes();
        $entetes['X-Demo-Duree-Ms'] = (string) round((microtime(true) - $debut) * 1000, 2);

        return new ReponseWeb($reponse->code(), $reponse->corps(), $entetes);
    }
}
