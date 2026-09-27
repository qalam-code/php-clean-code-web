<?php
/**
 * Controle de LIAISON sous la version de PHP de production.
 *
 *     php outils/compat.php [racine-src] [prefixe-namespace] [version-cible] [bootstrap]
 *
 * Exemples :
 *     C:\wamp64\bin\php\php7.0.33\php.exe outils\compat.php src App\Paiement 7.0
 *     php outils/compat.php squelette/src App\Exemple 7.0 autoload.php
 *
 * A EXECUTER AVEC LE BINAIRE DE LA VERSION VISEE, pas avec un autre.
 *
 * POURQUOI CE SCRIPT EXISTE. "php -l" ne verifie que la syntaxe : il ne
 * declare pas les classes, et ne peut donc voir aucune erreur de LIAISON --
 * visibilite reduite sur une methode heritee, signature incompatible avec le
 * parent, interface implementee a moitie, constante absente.
 *
 * Ce qu'il a coute de l'apprendre : une classe d'exception dont le
 * constructeur etait declare prive alors que Exception::__construct est
 * publique. PHP 8 l'accepte, PHP 7.0 la refuse -- et la refuse a la
 * DECLARATION, pas a l'usage. L'API entiere tombait au premier appel, sur
 * tous ses endpoints a la fois. Ni "php -l", ni une suite de tests verte sous
 * PHP 8 ne pouvaient le voir.
 *
 * Ce script declare toutes les classes trouvees et laisse PHP se plaindre.
 * Ce n'est pas volontairement fragile : un echec de liaison est fatal, le
 * script s'arrete en nommant la classe fautive -- exactement le message que
 * produirait le serveur.
 *
 * Volontairement ecrit en PHP 5 : il doit pouvoir demarrer sous n'importe
 * quelle version pour avoir une chance de dire ce qui ne va pas.
 */

$racine   = isset($argv[1]) ? $argv[1] : dirname(__DIR__) . '/src';
$prefixe  = isset($argv[2]) ? rtrim($argv[2], '\\') . '\\' : 'PhpCleanCode\\';
$cible    = isset($argv[3]) ? $argv[3] : '7.0';
$bootstrap = isset($argv[4]) ? $argv[4] : '';

if ($bootstrap !== '') {
    $bootstrap = realpath($bootstrap);
    if ($bootstrap === false || !is_file($bootstrap)) {
        echo 'Bootstrap introuvable.' . PHP_EOL;
        exit(2);
    }
    require_once $bootstrap;
}

$racine = realpath($racine);
if ($racine === false) {
    echo 'Racine introuvable.' . PHP_EOL;
    exit(2);
}

echo 'PHP ' . PHP_VERSION . '   |   cible ' . $cible . PHP_EOL;
echo 'Racine   : ' . $racine . PHP_EOL;
echo 'Prefixe  : ' . $prefixe . PHP_EOL;

$versionSuivante = implode('.', array(
    (int) strtok($cible, '.'),
    (int) strtok('.') + 1,
));

if (version_compare(PHP_VERSION, $versionSuivante, '>=')) {
    echo PHP_EOL;
    echo "ATTENTION : ce binaire n'est pas celui de la version visee." . PHP_EOL;
    echo "Les versions plus recentes acceptent des constructions que " . $cible . " refuse." . PHP_EOL;
    echo "Ce controle ne prouve donc rien. Relancez-le avec le php de production." . PHP_EOL;
    echo PHP_EOL;
}

spl_autoload_register(function ($classe) use ($racine, $prefixe) {
    if (strpos($classe, $prefixe) !== 0) {
        return;
    }
    $chemin = $racine . '/' . str_replace('\\', '/', substr($classe, strlen($prefixe))) . '.php';
    if (is_file($chemin)) {
        require_once $chemin;
    }
});

$fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine));
$noms     = array();

foreach ($fichiers as $fichier) {
    if ($fichier->getExtension() !== 'php') {
        continue;
    }
    $relatif = substr($fichier->getPathname(), strlen($racine) + 1, -4);
    $noms[]  = $prefixe . str_replace(array('/', '\\'), '\\', $relatif);
}

sort($noms);
$declarees = 0;

foreach ($noms as $nom) {
    if (class_exists($nom) || interface_exists($nom) || trait_exists($nom)) {
        $declarees++;
    } else {
        // Ni erreur fatale, ni classe declaree : le fichier ne contient pas
        // le type attendu. Le plus souvent, PSR-4 n'est pas respecte.
        echo '  MANQUANT  ' . $nom . PHP_EOL;
    }
}

echo PHP_EOL;
echo $declarees . ' sur ' . count($noms) . ' types declares sans erreur de liaison.' . PHP_EOL;

exit($declarees === count($noms) ? 0 : 1);

