<?php
/**
 * Autochargement PSR-4 sans Composer.
 *
 * La bibliotheque n'a aucune dependance : exiger "composer install" pour
 * s'en servir serait une barriere sans contrepartie, en particulier sur un
 * hebergement ou Composer n'est pas installe.
 *
 * Si vous utilisez Composer, ignorez ce fichier et requerez
 * vendor/autoload.php : l'autochargeur genere est plus rapide.
 */

spl_autoload_register(function ($classe) {
    $prefixe = 'PhpCleanCode\\';
    if (strpos($classe, $prefixe) !== 0) {
        return;
    }
    $chemin = __DIR__ . '/src/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require_once $chemin;
    }
});

