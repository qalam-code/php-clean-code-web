<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Http\AiguillageWeb;
use QalamCode\PhpCleanCodeWeb\Http\RouteurWeb;
use QalamCode\PhpCleanCodeWeb\Http\ServeurActifsVue;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$racine = dirname(dirname(__DIR__));
$repertoireVues = $racine . '/resources/vues';
$serveurActifs = new ServeurActifsVue($repertoireVues);
$aiguillage = new AiguillageWeb(function () use ($repertoireVues): RouteurWeb {
    $vues = new MoteurVue($repertoireVues);
    $definitionRoutes = require dirname(dirname(__DIR__)) . '/exemples/routes.php';
    return new RouteurWeb($definitionRoutes($vues));
}, null, $serveurActifs);

$reponse = $aiguillage->servir(Requete::depuisGlobales());
$reponse->envoyer();