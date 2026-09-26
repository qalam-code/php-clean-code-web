<?php
declare(strict_types=1);

/**
 * Auto-test de la bibliotheque.
 *
 *     php outils/verification.php
 *
 * A LANCER AVEC LE PHP DE VOTRE PRODUCTION, et pas seulement avec celui de
 * votre poste. Une bibliotheque verte sous PHP 8 et fatale sous PHP 7.0, cela
 * s'est deja vu -- c'est meme la raison d'etre du dernier controle de ce
 * fichier.
 *
 * Ce que ce fichier eprouve : la signature des jetons, la chaine
 * d'authentification, la paresse de la connexion, le contrat de reponse, le
 * routage, et la declarabilite des classes.
 */

require dirname(__DIR__) . '/autoload.php';

use PhpCleanCode\Application\Port\ResolveurActeurInterface;
use PhpCleanCode\Domain\Entite\Identite;
use PhpCleanCode\Domain\ErreurMetier;
use PhpCleanCode\Http\Aiguillage;
use PhpCleanCode\Http\Requete;
use PhpCleanCode\Http\Routeur;
use PhpCleanCode\Infrastructure\AuthentificationDifferee;
use PhpCleanCode\Infrastructure\FabriqueConnexion;
use PhpCleanCode\Infrastructure\JetonJwt;
use PhpCleanCode\Infrastructure\SurveillanceTimeout;
use PhpCleanCode\Presentation\Authentificateur;
use PhpCleanCode\Presentation\Presentateur\PresentateurCommun;
use PhpCleanCode\Presentation\ReponseHttp;
use PhpCleanCode\Test\AuthentificationFactice;
use PhpCleanCode\Test\JournalFactice;
use PhpCleanCode\Test\SurveillanceFactice;
use PhpCleanCode\Test\Verificateur;

$v = new Verificateur();
echo 'PHP ' . PHP_VERSION . PHP_EOL;

// ---------------------------------------------------------------------------
$v->section('JetonJwt -- ce qui doit etre accepte');

$jwt    = new JetonJwt('secret-de-test-suffisamment-long');
$jeton  = $jwt->emettre(['sub' => 42], 3600);
$charge = $jwt->verifier($jeton);

$v->egal(42, $charge['sub'], 'la charge revient intacte');
$v->vrai(isset($charge['iat']), 'iat est ajoute');
$v->egal(3, count(explode('.', $jeton)), 'trois segments');
$v->egal(false, strpos($jeton, '=') !== false, 'base64url : pas de remplissage');

// ---------------------------------------------------------------------------
$v->section('JetonJwt -- ce qui doit etre refuse');

$v->leve(ErreurMetier::JETON_MALFORME, function () use ($jwt) {
    return $jwt->verifier('pas-un-jeton');
}, 'chaine quelconque');

$v->leve(ErreurMetier::JETON_MAL_SIGNE, function () use ($jwt, $jeton) {
    // Charge modifiee : sub passe de 42 a 1. Le classique.
    $parts    = explode('.', $jeton);
    $parts[1] = rtrim(strtr(base64_encode((string) json_encode(
        ['sub' => 1, 'exp' => time() + 3600]
    )), '+/', '-_'), '=');
    return $jwt->verifier(implode('.', $parts));
}, 'charge modifiee : signature invalide');

$v->leve(ErreurMetier::JETON_MAL_SIGNE, function () use ($jwt, $jeton) {
    // "alg":"none" : le jeton s'annonce non signe. Une bibliotheque qui lit
    // l'entete pour choisir son algorithme l'accepte. Pas celle-ci.
    $enc = function ($d) {
        return rtrim(strtr(base64_encode((string) json_encode($d)), '+/', '-_'), '=');
    };
    return $jwt->verifier(
        $enc(['typ' => 'JWT', 'alg' => 'none']) . '.' . $enc(['sub' => 1, 'exp' => time() + 3600]) . '.'
    );
}, 'alg:none refuse');

$v->leve(ErreurMetier::JETON_MAL_SIGNE, function () use ($jeton) {
    return (new JetonJwt('un-autre-secret'))->verifier($jeton);
}, 'signe avec un autre secret');

$v->leve(ErreurMetier::JETON_EXPIRE, function () use ($jwt) {
    return $jwt->verifier($jwt->emettre(['sub' => 42], -1));
}, 'jeton expire');

$v->leve(ErreurMetier::JETON_MALFORME, function () use ($jwt) {
    // Sans exp, un jeton serait valide pour toujours.
    $enc = function ($d) {
        return rtrim(strtr(base64_encode((string) json_encode($d)), '+/', '-_'), '=');
    };
    $e = $enc(['typ' => 'JWT', 'alg' => 'HS256']);
    $c = $enc(['sub' => 42]);
    $s = rtrim(strtr(base64_encode(hash_hmac('sha256', $e . '.' . $c, 'secret-de-test-suffisamment-long', true)), '+/', '-_'), '=');
    return $jwt->verifier($e . '.' . $c . '.' . $s);
}, 'jeton sans exp refuse');

$refuseSecretVide = false;
try {
    new JetonJwt('');
} catch (InvalidArgumentException $e) {
    $refuseSecretVide = true;
}
$v->vrai($refuseSecretVide, 'un secret vide fait echouer la construction');

// ---------------------------------------------------------------------------
$v->section('Authentificateur');

$resolveur = new class implements ResolveurActeurInterface {
    public bool $connu = true;
    public int $appels = 0;

    public function resoudre(array $charge): ?Identite
    {
        $this->appels++;
        return $this->connu ? new Identite((int) $charge['sub'], 'test') : null;
    }
};

$avec = function (?string $entete) use ($jwt, $resolveur) {
    $entetes = $entete === null ? [] : ['Authorization' => $entete];
    return new Authentificateur(new Requete('GET', '/x', [], [], $entetes), $jwt, $resolveur);
};

$v->egal(42, $avec('Bearer ' . $jeton)->identifier()->id(), 'en-tete "Bearer xxx"');
$v->egal(42, $avec($jeton)->identifier()->id(), 'en-tete sans prefixe');
$v->egal(42, $avec('bearer ' . $jeton)->identifier()->id(), 'prefixe insensible a la casse');

$v->leve(ErreurMetier::JETON_ABSENT, function () use ($avec) {
    return $avec(null)->identifier();
}, 'en-tete absent');
$v->leve(ErreurMetier::JETON_ABSENT, function () use ($avec) {
    return $avec('   ')->identifier();
}, 'en-tete vide');

$resolveur->connu = false;
$v->leve(ErreurMetier::ACTEUR_INTROUVABLE, function () use ($avec, $jeton) {
    return $avec('Bearer ' . $jeton)->identifier();
}, 'compte supprime : jeton valide, acces refuse');
$resolveur->connu = true;

$resolveur->appels = 0;
$auth = $avec('Bearer ' . $jeton);
$auth->identifier();
$auth->identifier();
$v->egal(1, $resolveur->appels, 'le jeton n est verifie qu une fois par requete');

// ---------------------------------------------------------------------------
$v->section('Paresse -- ce qui ne doit PAS arriver trop tot');

$construit  = 0;
$differee   = new AuthentificationDifferee(function () use (&$construit) {
    $construit++;
    return AuthentificationFactice::acteur(7);
});
$v->egal(0, $construit, 'rien n est construit a l enveloppement');
$differee->identifier();
$v->egal(1, $construit, 'construit au premier identifier()');

// Le DSN est volontairement invalide : si la connexion s'ouvrait a la
// construction, la ligne suivante leverait.
$connexion = new FabriqueConnexion('mysql:host=255.255.255.255;dbname=neant', 'x', 'y', null);
$v->egal(false, $connexion->estOuverte(), 'aucune connexion avant le premier pdo()');

// ---------------------------------------------------------------------------
$v->section('Surveillance');

$s = SurveillanceFactice::epuiserApres(2);
$v->vrai($s->tempsRestant(), 'etape 1 autorisee');
$v->vrai($s->tempsRestant(), 'etape 2 autorisee');
$v->egal(false, $s->tempsRestant(), 'etape 3 refusee');

// La marge doit REELLEMENT reserver du temps : limite 10, marge 9 laisse 1s.
$reelle = new SurveillanceTimeout(10.0, 9.0);
$v->vrai($reelle->tempsRestant(), 'limite 10 marge 9 : il reste du temps');
$v->egal(false, (new SurveillanceTimeout(5.0, 5.0))->tempsRestant(), 'marge egale a la limite : plus rien');

// ---------------------------------------------------------------------------
$v->section('ReponseHttp');

$r = new ReponseHttp(200, ['b' => 2, 'a' => 1]);
$v->egal('{"b":2,"a":1}', $r->json(), 'l ordre des cles est preserve');
$v->egal(
    '{"v":"' . "r\xc3\xa9gl\xc3\xa9e" . '"}',
    (new ReponseHttp(200, ['v' => "r\xc3\xa9gl\xc3\xa9e"]))->json(),
    'accents rendus tels quels, pas en \\uXXXX'
);

$journal = new JournalFactice();
$journal->reussit = false;
$v->egal(false, $journal->enregistrer(1, 'x'), 'le journal rend false et NE LEVE PAS');

// ---------------------------------------------------------------------------
$v->section('Requete -- en-tetes serveur');

$serveurOriginal = $_SERVER;
$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/test',
    'HTTP_CONTENT_TYPE' => 'application/json',
    'REDIRECT_HTTP_AUTHORIZATION' => 'Bearer test',
];
try {
    $requeteServeur = Requete::depuisGlobales();
    $v->egal('Bearer test', $requeteServeur->entete('Authorization'), 'Authorization Apache recupere avec les autres en-tetes');
} finally {
    $_SERVER = $serveurOriginal;
}

// ---------------------------------------------------------------------------
$v->section('Architecture -- dependances vers l interieur');

$racineSource = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src';
$regles = [
    'Domain' => ['Application', 'Infrastructure', 'Presentation', 'Http'],
    'Application' => ['Infrastructure', 'Presentation', 'Http'],
    'Infrastructure' => ['Presentation', 'Http'],
];
$violations = [];

foreach ($regles as $couche => $interdites) {
    $racineCouche = $racineSource . DIRECTORY_SEPARATOR . $couche;
    if (!is_dir($racineCouche)) {
        continue;
    }

    $fichiers = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($racineCouche, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($fichiers as $fichier) {
        if (!$fichier->isFile() || $fichier->getExtension() !== 'php') {
            continue;
        }
        $lignes = file($fichier->getPathname(), FILE_IGNORE_NEW_LINES);
        foreach ($lignes as $numero => $ligne) {
            $import = ltrim($ligne);
            if (strpos($import, 'use PhpCleanCode\\') !== 0) {
                continue;
            }
            $parties = explode('\\', substr($import, strlen('use PhpCleanCode\\')));
            if (in_array($parties[0], $interdites, true)) {
                $relatif = substr($fichier->getPathname(), strlen($racineSource) + 1);
                $violations[] = $couche . '/' . $relatif . ':' . ($numero + 1)
                    . ' importe ' . $parties[0];
            }
        }
    }
}

$v->egal([], $violations, 'aucune dependance vers une couche exterieure interdite');

// ---------------------------------------------------------------------------
$v->section('Aiguillage');

$routeur = new Routeur();
$routeur->ajouter('ok', function () {
    return function () {
        return new ReponseHttp(200, ['statut' => 'succes']);
    };
}, function () {
    return new PresentateurCommun();
});
$routeur->ajouter('boum', function () {
    return function () {
        throw new ErreurMetier(ErreurMetier::RESSOURCE_INTROUVABLE, 'x');
    };
}, function () {
    return new PresentateurCommun();
});

$a = new Aiguillage($routeur, new PresentateurCommun());
$v->egal(200, $a->servir(new Requete('GET', '/ok'))->code(), 'route servie');
$v->egal(404, $a->servir(new Requete('GET', '/boum'))->code(), 'ErreurMetier traduite');
$v->egal(404, $a->servir(new Requete('GET', '/ailleurs'))->code(), 'chemin inconnu');
$v->egal(['ok', 'boum'], $routeur->chemins(), 'inventaire des routes');

// ---------------------------------------------------------------------------
$v->section('Declarabilite -- le piege qui a coute le plus cher');

/*
 * PHP 7.0 refuse qu'une methode redefinie reduise sa visibilite, y compris
 * pour un constructeur. Exception::__construct etant publique, une exception
 * dont le constructeur est prive ou protege ne se DECLARE PAS -- erreur
 * fatale au chargement, sur tous les endpoints a la fois.
 *
 * PHP 8 a relache cette regle. Le controle ci-dessous applique donc la regle
 * stricte quelle que soit la version qui l'execute : c'est le seul moyen de
 * s'en premunir depuis un poste plus recent que la production.
 */
foreach (['PhpCleanCode\Domain\ErreurMetier'] as $classe) {
    $reflexion   = new ReflectionClass($classe);
    $constructeur = $reflexion->getConstructor();
    $v->vrai(
        $constructeur !== null && $constructeur->isPublic(),
        $classe . '::__construct est publique (obligatoire des PHP 7.0)'
    );
}

$v->egal(
    'parametre manquant : numero',
    ErreurMetier::parametreManquant('numero')->getMessage(),
    'les fabriques nommees composent le message'
);
$v->vrai(
    ErreurMetier::jetonExpire()->estDeType(ErreurMetier::JETON_EXPIRE),
    'estDeType repond juste'
);

exit($v->bilan());

