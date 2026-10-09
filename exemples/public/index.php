<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Application\MessagesFlash;
use QalamCode\PhpCleanCodeWeb\Http\AiguillageWeb;
use QalamCode\PhpCleanCodeWeb\Http\CorsMiddleware;
use QalamCode\PhpCleanCodeWeb\Http\GestionnaireCsrf;
use QalamCode\PhpCleanCodeWeb\Http\RouteurWeb;
use QalamCode\PhpCleanCodeWeb\Http\ServeurActifsVue;
use QalamCode\PhpCleanCodeWeb\Infrastructure\SessionPhp;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;
use QalamCode\PhpCleanCodeWeb\Validation\ValidateurDonnees;

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$racine = dirname(dirname(__DIR__));
$repertoireVues = $racine . '/resources/vues';
$serveurActifs = new ServeurActifsVue($repertoireVues);
$cors = new CorsMiddleware(
    ['http://localhost:3000'],
    ['GET', 'POST'],
    ['Content-Type', 'X-CSRF-Token'],
    true,
    600,
    ['X-Demo-Duree-Ms']
);
$aiguillage = new AiguillageWeb(function () use ($racine, $repertoireVues): RouteurWeb {
    $session = new SessionPhp();
    $csrf = new GestionnaireCsrf($session);
    $messagesFlash = new MessagesFlash($session);
    $vues = new MoteurVue($repertoireVues);
    $definitionRoutes = require $racine . '/exemples/routes.php';
    return new RouteurWeb(
        $definitionRoutes($vues, $csrf, new ValidateurDonnees(), $messagesFlash),
        $csrf
    );
}, null, $serveurActifs, null, $cors);

$reponse = $aiguillage->servir(Requete::depuisGlobales());
$reponse->envoyer();
