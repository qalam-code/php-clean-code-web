<?php
declare(strict_types=1);

/**
 * Table des routes HTTP de l'API.
 *
 * Le service nomme ici est cable avec son controleur et son presentateur
 * dans src/Fabrique.php, qui reste la racine de composition du projet.
 */
return [
    [
        'chemin'   => 'login',
        'service'  => 'connexion',
        'methodes' => ['POST'],
    ],
    [
        'chemin'   => 'facture',
        'service'  => 'facture',
        'methodes' => ['GET', 'POST'],
    ],
];
