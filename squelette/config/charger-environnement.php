<?php
declare(strict_types=1);

/**
 * Charge les valeurs locales du fichier .env sans remplacer celles du serveur.
 * Le fichier suit la syntaxe INI simple, prise en charge par PHP sans dependance.
 */
$fichier = dirname(__DIR__) . '/.env';

if (!is_file($fichier)) {
    return;
}

$variables = @parse_ini_file($fichier, false, INI_SCANNER_RAW);
if (!is_array($variables)) {
    throw new \RuntimeException('Le fichier .env est invalide.');
}

foreach ($variables as $nom => $valeur) {
    if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $nom)) {
        throw new \RuntimeException('Nom de variable invalide dans le fichier .env.');
    }

    // Les variables du serveur ou du vhost ont priorite sur le fichier local.
    if (getenv($nom) !== false) {
        continue;
    }

    $valeur = (string) $valeur;
    putenv($nom . '=' . $valeur);
    $_ENV[$nom] = $valeur;
    $_SERVER[$nom] = $valeur;
}
