<?php
/**
 * Harnais de tests de caracterisation de l'API de paiement.
 *
 *   php run.php list                      liste les cas et leur niveau de risque
 *   php run.php record [options]          enregistre la reference depuis le serveur
 *   php run.php verify [options]          compare le serveur a la reference
 *
 * Par defaut, seuls les cas en lecture seule sont executes.
 *
 * Compatible PHP 7.0 strict : pas de types nullables, pas de void,
 * pas de proprietes typees, pas de fonctions flechees.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("Ce script ne s'execute qu'en ligne de commande.\n");
}

require_once __DIR__ . '/lib/CaracHttp.php';
require_once __DIR__ . '/lib/CaracNormalizer.php';
require_once __DIR__ . '/lib/CaracJwt.php';
require_once __DIR__ . '/lib/CaracReporter.php';
require_once __DIR__ . '/cases.php';

define('GOLDEN_DIR', __DIR__ . '/golden');
define('LASTRUN_DIR', __DIR__ . '/last-run');

$command = isset($argv[1]) ? $argv[1] : 'help';
$options = array(
    'inclure'          => array(),
    'filtre'           => '',
    'cas'              => array(),
    'couleur'          => true,
    'confirmerExterne' => false,
);

for ($i = 2; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if (strpos($arg, '--inclure=') === 0) {
        $options['inclure'] = array_filter(array_map('trim', explode(',', substr($arg, 10))));
    } elseif (strpos($arg, '--filtre=') === 0) {
        $options['filtre'] = substr($arg, 9);
    } elseif (strpos($arg, '--cas=') === 0) {
        $options['cas'] = array_filter(array_map('trim', explode(',', substr($arg, 6))));
    } elseif ($arg === '--sans-couleur') {
        $options['couleur'] = false;
    } elseif ($arg === '--confirmer-externe') {
        $options['confirmerExterne'] = true;
    } else {
        fwrite(STDERR, 'Option inconnue : ' . $arg . PHP_EOL);
        exit(2);
    }
}

if (!in_array($command, array('list', 'record', 'verify'), true)) {
    echo <<<AIDE

Harnais de tests de caracterisation de l'API de paiement.

  php run.php list                      liste les cas et leur niveau de risque
  php run.php record [options]          enregistre la reference depuis le serveur
  php run.php verify [options]          compare le serveur a la reference

Options :
  --inclure=mutant,externe              inclut les cas modifiant la base ou appelant un tiers
  --confirmer-externe                   obligatoire en plus de --inclure=externe
  --filtre=<texte>                      ne garde que les cas dont le nom contient <texte>
  --cas=nom1,nom2                       ne lance que ces cas
  --sans-couleur                        desactive les codes couleur ANSI


AIDE;
    exit($command === 'help' ? 0 : 2);
}

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "config.php introuvable.\nCopier config.sample.php en config.php et le renseigner.\n");
    exit(2);
}
$config = require $configPath;

$reporter = new CaracReporter($options['couleur']);
$http     = new CaracHttpClient($config['base_url'], $config['timeout']);
$allCases = caracterisation_cases($config);

if ($command === 'list') {
    $reporter->title('Cas de caracterisation (' . count($allCases) . ')');
    $groupe = '';
    foreach ($allCases as $case) {
        if ($case['groupe'] !== $groupe) {
            $groupe = $case['groupe'];
            echo PHP_EOL . '  [' . $groupe . ']' . PHP_EOL;
        }
        echo sprintf('    %-8s %-40s %s', strtoupper($case['risque']), $case['name'], $case['comparer']) . PHP_EOL;
    }
    echo PHP_EOL;
    exit(0);
}

$risquesActifs = array_merge(array('sur'), $options['inclure']);
$selection     = array();
$aExterne      = false;
$besoinToken   = false;

foreach ($allCases as $case) {
    if (!in_array($case['risque'], $risquesActifs, true)) {
        continue;
    }
    if ($options['cas'] !== array() && !in_array($case['name'], $options['cas'], true)) {
        continue;
    }
    if ($options['filtre'] !== '' && strpos($case['name'], $options['filtre']) === false) {
        continue;
    }
    $selection[] = $case;
    if ($case['risque'] === 'externe') {
        $aExterne = true;
    }
    if ($case['auth'] === 'valide') {
        $besoinToken = true;
    }
}

if ($aExterne && !$options['confirmerExterne']) {
    fwrite(STDERR,
        "Des cas de risque 'externe' sont selectionnes.\n"
      . "Ils creditent un compteur REEL chez le fournisseur et inserent en base.\n"
      . "Ajouter --confirmer-externe pour les executer, ou retirer 'externe' de --inclure.\n");
    exit(2);
}

// -----------------------------------------------------------------------------
// Jeton d'authentification, obtenu une seule fois
// -----------------------------------------------------------------------------
$tokenValide = null;
$erreurLogin = '';

if ($besoinToken) {
    $reponseLogin = $http->send('POST', '/login', array(), array(
        'username' => $config['auth']['username'],
        'pwd'      => $config['auth']['pwd'],
    ));
    $corpsLogin = json_decode($reponseLogin->body, true);

    if ($reponseLogin->status === 200 && is_array($corpsLogin) && !empty($corpsLogin['token'])) {
        $tokenValide = $corpsLogin['token'];
    } else {
        $erreurLogin = 'HTTP ' . $reponseLogin->status
            . ($reponseLogin->failed() ? ' / ' . $reponseLogin->error : '');
    }
}

// -----------------------------------------------------------------------------
// Execution
// -----------------------------------------------------------------------------
if (!is_dir(GOLDEN_DIR)) {
    mkdir(GOLDEN_DIR, 0777, true);
}
if (!is_dir(LASTRUN_DIR)) {
    mkdir(LASTRUN_DIR, 0777, true);
}

$reporter->title(
    ($command === 'record' ? 'ENREGISTREMENT DE LA REFERENCE' : 'VERIFICATION DU CONTRAT')
    . '  -  ' . $config['base_url'] . '  -  ' . count($selection) . ' cas'
);

if ($erreurLogin !== '') {
    $reporter->info('Authentification indisponible (' . $erreurLogin . ') : les cas authentifies seront ignores.');
}

$groupe = '';
foreach ($selection as $case) {
    if ($case['groupe'] !== $groupe) {
        $groupe = $case['groupe'];
        echo PHP_EOL . '  [' . $groupe . ']' . PHP_EOL;
    }

    $raison = raison_ignorer($case, $config, $tokenValide);
    if ($raison !== '') {
        $reporter->skip($case['name'], $raison);
        continue;
    }

    $reponse = $http->send(
        $case['method'],
        $case['path'],
        isset($case['query']) ? $case['query'] : array(),
        isset($case['body']) ? $case['body'] : null,
        entetes_auth($case['auth'], $tokenValide, $config)
    );

    if ($reponse->failed()) {
        $reporter->fail($case['name'], array('transport    ' . $reponse->error));
        continue;
    }

    $normalizer = new CaracNormalizer($case['masquer']);
    $observe = array(
        'case'         => $case['name'],
        'horodatage'   => date('c'),
        'requete'      => array(
            'method' => $case['method'],
            'path'   => $case['path'],
            'query'  => isset($case['query']) ? $case['query'] : array(),
            'body'   => isset($case['body']) ? $case['body'] : null,
            'auth'   => $case['auth'],
        ),
        'status'       => $reponse->status,
        'content_type' => isset($reponse->headers['content-type']) ? $reponse->headers['content-type'] : '',
        'comparer'     => $case['comparer'],
        'body'         => $normalizer->normalize($reponse->body, $case['comparer']),
        'extrait_brut' => substr($reponse->body, 0, 1000),
        'note'         => $case['note'],
    );

    ecrire_json(LASTRUN_DIR . '/' . $case['name'] . '.json', $observe);
    $cheminGolden = GOLDEN_DIR . '/' . $case['name'] . '.json';

    if ($command === 'record') {
        ecrire_json($cheminGolden, $observe);
        $reporter->pass($case['name'], 'HTTP ' . $reponse->status . '  (' . $reponse->durationMs . ' ms)');
        continue;
    }

    if (!is_file($cheminGolden)) {
        $reporter->fail($case['name'], array('reference absente - lancer d abord : php run.php record'));
        continue;
    }

    $reference = json_decode(file_get_contents($cheminGolden), true);
    if (!is_array($reference)) {
        $reporter->fail($case['name'], array('reference illisible : ' . $cheminGolden));
        continue;
    }

    $ecarts = CaracReporter::diff($reference, $observe);
    if ($ecarts === array()) {
        $reporter->pass($case['name'], 'HTTP ' . $reponse->status . '  (' . $reponse->durationMs . ' ms)');
    } else {
        $reporter->fail($case['name'], $ecarts);
    }
}

exit($reporter->summary());

// -----------------------------------------------------------------------------
// Fonctions (declarations hissees a la compilation, d'ou leur place ici)
// -----------------------------------------------------------------------------

/**
 * Determine si un cas doit etre ignore faute de prerequis.
 *
 * @param array  $case
 * @param array  $config
 * @param string $tokenValide null si indisponible
 * @return string chaine vide si le cas doit etre execute
 */
function raison_ignorer(array $case, array $config, $tokenValide)
{
    foreach ($case['requiert'] as $cle) {
        if (!isset($config['fixtures'][$cle]) || $config['fixtures'][$cle] === '') {
            return 'fixture "' . $cle . '" non renseignee dans config.php';
        }
    }
    if ($case['auth'] === 'valide' && $tokenValide === null) {
        return 'aucun jeton valide disponible';
    }
    if (in_array($case['auth'], array('signature', 'expiree'), true) && empty($config['jwt_secret'])) {
        return 'jwt_secret non renseigne dans config.php';
    }
    if ($case['auth'] === 'aucune' && $case['path'] === '/login'
        && ($config['auth']['username'] === '' || $config['auth']['pwd'] === '')) {
        return 'identifiants non renseignes dans config.php';
    }
    return '';
}

/**
 * Construit l'en-tete Authorization correspondant au mode demande.
 *
 * @param string $mode
 * @param string $tokenValide
 * @param array  $config
 * @return array
 */
function entetes_auth($mode, $tokenValide, array $config)
{
    $payload = array(
        'username' => $config['auth']['username'],
        'id_user'  => 1,
        'compagny' => 'FlexEau',
    );

    switch ($mode) {
        case 'valide':
            return array('Authorization: Bearer ' . $tokenValide);
        case 'malformee':
            return array('Authorization: Bearer ceci-nest-pas-un-jeton-jwt');
        case 'signature':
            return array('Authorization: Bearer ' . CaracJwt::forgeWithWrongSignature($payload));
        case 'expiree':
            return array('Authorization: Bearer ' . CaracJwt::forge($payload, $config['jwt_secret'], -3600));
        default:
            return array();
    }
}

/**
 * @param string $chemin
 * @param array  $donnees
 * @return void
 */
function ecrire_json($chemin, array $donnees)
{
    $json = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($chemin, $json . PHP_EOL);
}

