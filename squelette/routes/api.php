<?php
declare(strict_types=1);

/**
 * Table des routes HTTP de l'API.
 *
 * Le nom du service est le suffixe des classes Controleur et Presentateur.
 * Leurs fabriques explicites sont enregistrees dans src/Fabrique.php.
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
