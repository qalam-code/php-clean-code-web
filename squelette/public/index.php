<?php
declare(strict_types=1);

/**
 * Point d'entree unique.
 *
 * TOUT PASSE PAR ICI, et c'est le seul fichier expose au web. Les sources
 * sont hors de public/ : un fichier qui n'est pas servi ne peut pas etre
 * lu par erreur, quelle que soit la configuration du serveur.
 *
 * Ce fichier ne doit jamais grossir. S'il commence a contenir des "if" sur
 * le chemin demande, la logique est en train de remonter du routeur vers
 * lui.
 */

require __DIR__ . '/../autoload.php';

use App\Exemple\Fabrique;
use PhpCleanCode\Http\Aiguillage;
use PhpCleanCode\Http\Requete;
use PhpCleanCode\Presentation\Presentateur\PresentateurCommun;

// display_errors expose les chemins du serveur et parfois les identifiants
// de connexion dans le message d'une exception PDO. On journalise, on
// n'affiche pas.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/../config/charger-environnement.php';

$requete = Requete::depuisGlobales();

$aiguillage = new Aiguillage(
    (new Fabrique($requete))->routeur(),
    // Presentateur de secours : chemin inconnu, methode refusee, et pannes
    // survenues avant qu'une route soit identifiee. Il n'a aucune
    // dependance, donc rien qui puisse echouer a son tour.
    new PresentateurCommun(),
    function (Throwable $e) {
        // Ce qui n'est pas rendu au consommateur doit etre ecrit quelque
        // part, sinon l'incident est invisible.
        error_log('[' . get_class($e) . '] ' . $e->getMessage() . ' @ '
            . $e->getFile() . ':' . $e->getLine());
    }
);

$aiguillage->servir($requete)->envoyer();

