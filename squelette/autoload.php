<?php
/**
 * Autochargement des deux espaces de noms, sans Composer.
 *
 * Si vous utilisez Composer, remplacez le contenu par :
 *     require __DIR__ . '/vendor/autoload.php';
 */

$prefixes = [
    'App\\Exemple\\'  => __DIR__ . '/src/',
    'PhpCleanCode\\'  => dirname(__DIR__) . '/src/',
];

spl_autoload_register(function ($classe) use ($prefixes) {
    foreach ($prefixes as $prefixe => $racine) {
        if (strpos($classe, $prefixe) !== 0) {
            continue;
        }
        $chemin = $racine . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
        if (is_file($chemin)) {
            require_once $chemin;
            return;
        }
    }
});

