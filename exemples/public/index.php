<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Http\RouteurWeb;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$vues = new MoteurVue(dirname(dirname(__DIR__)) . '/exemples/vues');
$definitionRoutes = require dirname(dirname(__DIR__)) . '/exemples/routes.php';
$routes = $definitionRoutes($vues);
$reponse = (new RouteurWeb($routes))->servir(Requete::depuisGlobales());
$reponse->envoyer();