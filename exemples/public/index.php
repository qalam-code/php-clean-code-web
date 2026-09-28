<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Http\AiguillageWeb;
use QalamCode\PhpCleanCodeWeb\Http\RouteurWeb;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$aiguillage = new AiguillageWeb(function (): RouteurWeb {
    $vues = new MoteurVue(dirname(dirname(__DIR__)) . '/exemples/vues');
    $definitionRoutes = require dirname(dirname(__DIR__)) . '/exemples/routes.php';
    return new RouteurWeb($definitionRoutes($vues));
});

$reponse = $aiguillage->servir(Requete::depuisGlobales());
$reponse->envoyer();