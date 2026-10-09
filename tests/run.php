<?php
declare(strict_types=1);

use PhpCleanCode\Http\Requete;
use QalamCode\PhpCleanCodeWeb\Application\MessagesFlash;
use QalamCode\PhpCleanCodeWeb\Application\Port\StockageSession;
use QalamCode\PhpCleanCodeWeb\Http\AiguillageWeb;
use QalamCode\PhpCleanCodeWeb\Http\CorsMiddleware;
use QalamCode\PhpCleanCodeWeb\Http\EnTetesSecuriteMiddleware;
use QalamCode\PhpCleanCodeWeb\Http\GestionnaireCsrf;
use QalamCode\PhpCleanCodeWeb\Http\GenerateurUrl;
use QalamCode\PhpCleanCodeWeb\Http\GroupeRoutes;
use QalamCode\PhpCleanCodeWeb\Http\RouteurWeb;
use QalamCode\PhpCleanCodeWeb\Http\ServeurActifsVue;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseRedirection;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseWeb;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;

require dirname(__DIR__) . '/vendor/autoload.php';

/** Stockage en memoire pour tester les composants sans demarrer une session PHP. */
final class SessionMemoireTest implements StockageSession
{
    private $valeurs = [];

    public function lire(string $cle, $defaut = null)
    {
        return array_key_exists($cle, $this->valeurs) ? $this->valeurs[$cle] : $defaut;
    }

    public function ecrire(string $cle, $valeur)
    {
        $this->valeurs[$cle] = $valeur;
    }
}

/** Controleur minimal pour verifier le callable [$objet, 'methode'] utilise par une route. */
final class ControleurRouteTest
{
    public $parametresRecus = [];

    public function saluer(Requete $requete, array $parametres): ReponseHtml
    {
        $this->parametresRecus = $parametres;
        return new ReponseHtml(200, 'Bonjour ' . $parametres['nom']);
    }
}

$nombreTests = 0;
$echecs = [];

$verifier = function (bool $condition, string $nom) use (&$nombreTests, &$echecs) {
    $nombreTests++;
    if ($condition) {
        echo 'OK   ' . $nom . PHP_EOL;
        return;
    }
    $echecs[] = $nom;
    echo 'ECHEC ' . $nom . PHP_EOL;
};

// Une methode de controleur callable doit recevoir les parametres extraits du chemin.
$controleurRoute = new ControleurRouteTest();
$routeur = new RouteurWeb([
    [
        'chemin' => '/bonjour/{nom}',
        'methodes' => ['GET'],
        'action' => [$controleurRoute, 'saluer'],
    ],
]);
$reponse = $routeur->servir(new Requete('GET', '/bonjour/Awa'));
$verifier(
    $reponse->code() === 200 && $controleurRoute->parametresRecus === ['nom' => 'Awa'],
    'methode de controleur avec route dynamique et parametre de chemin'
);
$reponseHeadRoute = $routeur->servir(new Requete('HEAD', '/bonjour/Awa'));
$verifier(
    $reponseHeadRoute->code() === 200
        && $reponseHeadRoute->corps() === ''
        && $reponseHeadRoute->entetes()['Content-Type'] === 'text/html; charset=utf-8',
    'HEAD reutilise la route GET, conserve ses entetes et ne retourne pas de corps'
);

// Une contrainte de paramètre rejette les segments qui ne respectent pas le format annoncé.
$actionsArticle = 0;
$routeurContrainte = new RouteurWeb([
    [
        'chemin' => '/articles/{id}',
        'methodes' => ['GET'],
        'contraintes' => ['id' => '[0-9]+'],
        'action' => function (Requete $requete, array $parametres) use (&$actionsArticle): ReponseHtml {
            $actionsArticle++;
            return new ReponseHtml(200, $parametres['id']);
        },
    ],
]);
$articleNumerique = $routeurContrainte->servir(new Requete('GET', '/articles/42'));
$articleNonNumerique = $routeurContrainte->servir(new Requete('GET', '/articles/abc'));
$verifier(
    $articleNumerique->code() === 200
        && $articleNumerique->corps() === '42'
        && $articleNonNumerique->code() === 404
        && $actionsArticle === 1,
    'contrainte de route numerique refuse un segment non conforme avant l action'
);

// Les middleware s'exécutent autour de l'action et peuvent aussi l'interrompre.
$ordreMiddleware = [];
$actionMiddlewareAppelee = false;
$routeurMiddleware = new RouteurWeb([
    [
        'chemin' => '/protege',
        'methodes' => ['GET'],
        'middleware' => [
            function (Requete $requete, array $parametres, callable $suite) use (&$ordreMiddleware): ReponseWeb {
                $ordreMiddleware[] = 'avant';
                $reponse = $suite();
                $ordreMiddleware[] = 'apres';
                return $reponse;
            },
            function (Requete $requete, array $parametres, callable $suite) use (&$ordreMiddleware): ReponseWeb {
                $ordreMiddleware[] = 'interieur';
                return $suite();
            },
        ],
        'action' => function () use (&$ordreMiddleware, &$actionMiddlewareAppelee): ReponseHtml {
            $ordreMiddleware[] = 'action';
            $actionMiddlewareAppelee = true;
            return new ReponseHtml(200, 'autorise');
        },
    ],
    [
        'chemin' => '/refuse',
        'methodes' => ['GET'],
        'middleware' => [function (): ReponseHtml {
            return new ReponseHtml(403, 'refuse');
        }],
        'action' => function () use (&$actionMiddlewareAppelee): ReponseHtml {
            $actionMiddlewareAppelee = true;
            return new ReponseHtml(200, 'ne doit pas etre appele');
        },
    ],
]);
$reponseMiddleware = $routeurMiddleware->servir(new Requete('GET', '/protege'));
$actionMiddlewareAppelee = false;
$reponseRefuseeMiddleware = $routeurMiddleware->servir(new Requete('GET', '/refuse'));
$verifier(
    $reponseMiddleware->code() === 200
        && $ordreMiddleware === ['avant', 'interieur', 'action', 'apres']
        && $reponseRefuseeMiddleware->code() === 403
        && !$actionMiddlewareAppelee,
    'middleware entoure l action ou retourne une reponse de refus'
);

// Un chemin absent retourne 404 ; un chemin reconnu avec une autre methode retourne 405 et Allow.
$verifier($routeur->servir(new Requete('GET', '/absent'))->code() === 404, 'route absente retourne 404');
$reponseMethode = $routeur->servir(new Requete('POST', '/bonjour/Awa'));
$verifier(
    $reponseMethode->code() === 405 && $reponseMethode->entetes()['Allow'] === 'GET, HEAD, OPTIONS',
    'methode non autorisee retourne 405 avec Allow'
);
$reponseOptions = $routeur->servir(new Requete('OPTIONS', '/bonjour/Awa'));
$verifier(
    $reponseOptions->code() === 204
        && $reponseOptions->corps() === ''
        && $reponseOptions->entetes()['Allow'] === 'GET, HEAD, OPTIONS',
    'OPTIONS annonce les methodes disponibles pour une route connue'
);

// La politique CORS ajoute les en-têtes aux réponses réelles et aux précontrôles valides.
$cors = new CorsMiddleware(
    ['https://front.example'],
    ['GET', 'POST'],
    ['Content-Type', 'X-Trace'],
    true,
    600,
    ['X-Request-Id']
);
$routeurCors = new RouteurWeb([
    [
        'chemin' => '/cors',
        'methodes' => ['GET', 'POST'],
        'csrf' => false,
        'action' => function (): ReponseHtml {
            return new ReponseHtml(200, 'ok', ['X-Request-Id' => 'req-1']);
        },
    ],
]);
$aiguillageCors = new AiguillageWeb(function () use ($routeurCors): RouteurWeb {
    return $routeurCors;
}, null, null, null, $cors);
$reponseCorsSimple = $aiguillageCors->servir(new Requete(
    'GET', '/cors', [], [], ['Origin' => 'https://front.example']
));
$reponseCorsPrecontrole = $aiguillageCors->servir(new Requete('OPTIONS', '/cors', [], [], [
    'Origin' => 'https://front.example',
    'Access-Control-Request-Method' => 'POST',
    'Access-Control-Request-Headers' => 'Content-Type, X-Trace',
]));
$verifier(
    $reponseCorsSimple->entetes()['Access-Control-Allow-Origin'] === 'https://front.example'
        && $reponseCorsSimple->entetes()['Access-Control-Allow-Credentials'] === 'true'
        && $reponseCorsSimple->entetes()['Access-Control-Expose-Headers'] === 'X-Request-Id'
        && $reponseCorsPrecontrole->code() === 204
        && $reponseCorsPrecontrole->entetes()['Access-Control-Allow-Methods'] === 'GET, POST'
        && $reponseCorsPrecontrole->entetes()['Access-Control-Allow-Headers'] === 'Content-Type, X-Trace'
        && $reponseCorsPrecontrole->entetes()['Access-Control-Max-Age'] === '600',
    'CORS ajoute les en-tetes autorises aux reponses et au precontrole OPTIONS'
);
$reponseCorsOrigineRefusee = $aiguillageCors->servir(new Requete(
    'GET', '/cors', [], [], ['Origin' => 'https://intrus.example']
));
$verifier(
    !isset($reponseCorsOrigineRefusee->entetes()['Access-Control-Allow-Origin']),
    'CORS ne partage pas la reponse avec une origine non autorisee'
);
$securitePersonnalisee = new EnTetesSecuriteMiddleware([
    'X-Frame-Options' => 'SAMEORIGIN',
    'Referrer-Policy' => null,
    'Content-Security-Policy' => "default-src 'self'",
]);
$aiguillageSecurise = new AiguillageWeb(function () use ($routeurCors): RouteurWeb {
    return $routeurCors;
}, null, null, null, null, $securitePersonnalisee);
$reponseSecurisee = $aiguillageSecurise->servir(new Requete('GET', '/cors'));
$verifier(
    $reponseSecurisee instanceof ReponseHtml
        && $reponseSecurisee->entetes()['X-Content-Type-Options'] === 'nosniff'
        && $reponseSecurisee->entetes()['X-Frame-Options'] === 'SAMEORIGIN'
        && !isset($reponseSecurisee->entetes()['Referrer-Policy'])
        && $reponseSecurisee->entetes()['Content-Security-Policy'] === "default-src 'self'",
    'en-tetes de securite configurables preservent le type ReponseHtml'
);
$aiguillageSansSecurite = new AiguillageWeb(function () use ($routeurCors): RouteurWeb {
    return $routeurCors;
}, null, null, null, null, false);
$reponseSansSecurite = $aiguillageSansSecurite->servir(new Requete('GET', '/cors'));
$verifier(
    $reponseSansSecurite instanceof ReponseHtml
        && !isset($reponseSansSecurite->entetes()['X-Content-Type-Options']),
    'en-tetes de securite desactivables explicitement'
);
$corsIdentifiantsWildcardRefuse = false;
try {
    new CorsMiddleware(['*'], ['GET'], [], true);
} catch (InvalidArgumentException $erreur) {
    $corsIdentifiantsWildcardRefuse = true;
}
$verifier($corsIdentifiantsWildcardRefuse, 'CORS refuse wildcard avec identifiants');

// Une fabrique d'erreurs remplace les pages par defaut sans changer les statuts HTTP.
$statutsPersonnalises = [];
$fabriqueErreurPersonnalisee = function (int $code, array $contexte) use (&$statutsPersonnalises): ReponseHtml {
    $statutsPersonnalises[] = $code;
    $entetes = $code === 405 ? ['Allow' => implode(', ', $contexte['methodes'])] : [];
    return new ReponseHtml($code, 'Page personnalisee ' . $code, $entetes);
};
$routeurErreursPersonnalisees = new RouteurWeb([
    [
        'chemin' => '/connue',
        'methodes' => ['GET'],
        'action' => function () {
            return new ReponseHtml(200, 'ok');
        },
    ],
], null, $fabriqueErreurPersonnalisee);
$reponse404Personnalisee = $routeurErreursPersonnalisees->servir(new Requete('GET', '/inconnue'));
$reponse405Personnalisee = $routeurErreursPersonnalisees->servir(new Requete('POST', '/connue'));
$verifier(
    $reponse404Personnalisee->code() === 404
        && $reponse404Personnalisee->corps() === 'Page personnalisee 404'
        && $reponse405Personnalisee->code() === 405
        && $reponse405Personnalisee->entetes()['Allow'] === 'GET, HEAD, OPTIONS'
        && $statutsPersonnalises === [404, 405],
    'pages 404 et 405 personnalisables tout en gardant leur statut'
);

// Un groupe applique un prefixe et une politique CSRF par defaut, sans retirer le choix a chaque route.
$sessionGroupe = new SessionMemoireTest();
$csrfGroupe = new GestionnaireCsrf($sessionGroupe);
$jetonGroupe = $csrfGroupe->jeton();
$appelsGroupe = [];
$ordreGroupeMiddleware = [];
$middlewareGroupe = function (Requete $requete, array $parametres, callable $suite) use (&$ordreGroupeMiddleware): ReponseWeb {
    $ordreGroupeMiddleware[] = 'groupe-avant';
    $reponse = $suite();
    $ordreGroupeMiddleware[] = 'groupe-apres';
    return $reponse;
};
$middlewareRouteLocale = function (Requete $requete, array $parametres, callable $suite) use (&$ordreGroupeMiddleware): ReponseWeb {
    $ordreGroupeMiddleware[] = 'route-avant';
    $reponse = $suite();
    $ordreGroupeMiddleware[] = 'route-apres';
    return $reponse;
};
$routesGroupees = GroupeRoutes::avecPrefixe('/admin', [
    [
        'chemin' => '/articles',
        'methodes' => ['POST'],
        'middleware' => [$middlewareRouteLocale],
        'action' => function () use (&$appelsGroupe, &$ordreGroupeMiddleware): ReponseHtml {
            $appelsGroupe[] = 'articles';
            $ordreGroupeMiddleware[] = 'articles';
            return new ReponseHtml(200, 'articles');
        },
    ],
    [
        'chemin' => '/sante',
        'methodes' => ['POST'],
        'csrf' => false,
        'action' => function () use (&$appelsGroupe, &$ordreGroupeMiddleware): ReponseHtml {
            $appelsGroupe[] = 'sante';
            $ordreGroupeMiddleware[] = 'sante';
            return new ReponseHtml(200, 'sante');
        },
    ],
], ['csrf' => true, 'middleware' => [$middlewareGroupe]]);
$routeurGroupe = new RouteurWeb($routesGroupees, $csrfGroupe);
$postSansJeton = $routeurGroupe->servir(new Requete('POST', '/admin/articles'));
$postAvecJeton = $routeurGroupe->servir(new Requete('POST', '/admin/articles', ['_csrf' => $jetonGroupe]));
$postSansProtection = $routeurGroupe->servir(new Requete('POST', '/admin/sante'));
$verifier(
    $routesGroupees[0]['chemin'] === '/admin/articles'
        && isset($routesGroupees[1]['csrf'])
        && $routesGroupees[1]['csrf'] === false
        && $postSansJeton->code() === 403
        && $postAvecJeton->code() === 200
        && $postSansProtection->code() === 200
        && $ordreGroupeMiddleware === [
            'groupe-avant', 'route-avant', 'articles', 'route-apres', 'groupe-apres',
            'groupe-avant', 'sante', 'groupe-apres',
        ]
        && $appelsGroupe === ['articles', 'sante'],
    'groupe de routes partage le prefixe, CSRF et middleware avant ceux de chaque route'
);

// Les routes nommees centralisent les chemins et encodent chaque parametre comme un segment.
$generateurUrl = new GenerateurUrl([
    ['nom' => 'article.detail', 'chemin' => '/articles/{id}', 'methodes' => ['GET'], 'action' => function () {}],
    ['nom' => 'article.numeric', 'chemin' => '/articles/{id}', 'contraintes' => ['id' => '[0-9]+'], 'methodes' => ['GET'], 'action' => function () {}],
]);
$erreurParametreUrl = false;
try {
    $generateurUrl->pour('article.detail', ['id' => 'a/b']);
} catch (InvalidArgumentException $erreur) {
    $erreurParametreUrl = true;
}
$erreurContrainteUrl = false;
try {
    $generateurUrl->pour('article.numeric', ['id' => 'abc']);
} catch (InvalidArgumentException $erreur) {
    $erreurContrainteUrl = true;
}
$verifier(
    $generateurUrl->pour('article.detail', ['id' => 'a b']) === '/articles/a%20b'
        && $generateurUrl->pour('article.detail', ['id' => 42], ['recherche' => 'a b', 'page' => 2])
            === '/articles/42?recherche=a%20b&page=2'
        && $generateurUrl->pour('article.numeric', ['id' => 42]) === '/articles/42'
        && $erreurParametreUrl
        && $erreurContrainteUrl,
    'generateur URL encode chemin et query string et respecte les contraintes des routes nommees'
);

// Un POST HTML est explicitement redirige par l'action, tandis qu'une reponse non HTML reste intacte.
$routeurPost = new RouteurWeb([
    [
        'chemin' => '/formulaire',
        'methodes' => ['POST'],
        'csrf' => false,
        'action' => function (): ReponseHtml {
            return new ReponseHtml(200, '<p>OK</p>');
        },
    ],
    [
        'chemin' => '/ajax',
        'methodes' => ['POST'],
        'csrf' => false,
        'action' => function (): ReponseWeb {
            return new ReponseWeb(200, '{"ok":true}', ['Content-Type' => 'application/json']);
        },
    ],
]);
$reponseHtmlPost = $routeurPost->servir(new Requete('POST', '/formulaire'));
$reponseAjax = $routeurPost->servir(new Requete('POST', '/ajax'));
$verifier($reponseHtmlPost instanceof ReponseHtml && $reponseHtmlPost->code() === 200, 'le routeur ne redirige pas implicitement une reponse HTML');
$verifier($reponseAjax->code() === 200 && $reponseAjax->corps() === '{"ok":true}', 'une reponse AJAX est conservee');

// La protection CSRF bloque une action avant son execution si le jeton manque ou est invalide.
$session = new SessionMemoireTest();
$csrf = new GestionnaireCsrf($session);
$jeton = $csrf->jeton();
$appelsAction = 0;
$routeurCsrf = new RouteurWeb([
    [
        'chemin' => '/proteger',
        'methodes' => ['POST'],
        'csrf' => true,
        'action' => function () use (&$appelsAction): ReponseHtml {
            $appelsAction++;
            return new ReponseHtml(200, 'ok');
        },
    ],
], $csrf);
$refusee = $routeurCsrf->servir(new Requete('POST', '/proteger'));
$verifier($refusee->code() === 403 && $appelsAction === 0, 'CSRF invalide retourne 403 avant l action');
$autorisee = $routeurCsrf->servir(new Requete('POST', '/proteger', ['_csrf' => $jeton]));
$verifier($autorisee->code() === 200 && $appelsAction === 1, 'CSRF valide permet l action');

// Une redirection flash est locale, utilise le statut 303 et ne divulgue pas une destination externe.
$redirection = new ReponseRedirection('/bonjour');
$verifier(
    $redirection->code() === 303 && $redirection->entetes()['Location'] === '/bonjour',
    'redirection locale renvoie 303 et Location'
);
$destinationExterneRefusee = false;
try {
    new ReponseRedirection('https://example.com');
} catch (InvalidArgumentException $erreur) {
    $destinationExterneRefusee = true;
}
$verifier($destinationExterneRefusee, 'redirection externe refusee');

// Un message flash ne peut etre consomme qu'une fois.
$flash = new MessagesFlash($session);
$flash->ajouter('success', 'Enregistre');
$verifier($flash->consommer('success') === 'Enregistre', 'message flash disponible apres redirection');
$verifier($flash->consommer('success') === null, 'message flash efface apres consommation');

// Les marqueurs HTML sont echappes, puis le layout compose la page avec ses actifs locaux.
$moteurVue = new MoteurVue(dirname(__DIR__) . '/resources/vues');
$donneesVue = [
    'nom' => '<Awa>',
    'nomAffiche' => '<script>alert(1)</script>',
    'messageSucces' => '<ok &>',
    'erreur' => null,
    'csrfField' => '_csrf',
    'csrfToken' => 'jeton-test',
    'titre' => 'Bonjour',
];
$vueSimple = $moteurVue->rendre('bonjour', $donneesVue);
$verifier(
    strpos($vueSimple, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false
        && strpos($vueSimple, '<script>alert(1)</script>') === false
        && strpos($vueSimple, 'value="&lt;Awa&gt;"') !== false,
    'les donnees injectees dans une vue HTML sont echappees'
);
$pageComplete = $moteurVue->rendreAvecLayout('bonjour', 'layouts/principal', $donneesVue);
$verifier(
    strpos($pageComplete, '<main>') !== false
        && strpos($pageComplete, '<h1>Bonjour, &lt;script&gt;alert(1)&lt;/script&gt; !</h1>') !== false
        && strpos($pageComplete, '/assets/vues/bonjour/style.css') !== false
        && strpos($pageComplete, '/assets/vues/bonjour/script.js') !== false
        && strpos($pageComplete, ' defer') !== false,
    'le layout integre le composant et ses URLs CSS/JS'
);

// Parcours integre de l'exemple : point d'entree web, route, controleur, vue, CSRF et flash.
$racineProjet = dirname(__DIR__);
$sessionWeb = new SessionMemoireTest();
$csrfWeb = new GestionnaireCsrf($sessionWeb);
$flashWeb = new MessagesFlash($sessionWeb);
$vuesWeb = new MoteurVue($racineProjet . '/resources/vues');
$definitionRoutes = require $racineProjet . '/exemples/routes.php';
$constructionsRouteur = 0;
$aiguillageWeb = new AiguillageWeb(function () use (
    &$constructionsRouteur,
    $definitionRoutes,
    $vuesWeb,
    $csrfWeb,
    $flashWeb
): RouteurWeb {
    $constructionsRouteur++;
    return new RouteurWeb(
        $definitionRoutes($vuesWeb, $csrfWeb, new QalamCode\PhpCleanCodeWeb\Validation\ValidateurDonnees(), $flashWeb),
        $csrfWeb
    );
}, null, new ServeurActifsVue($racineProjet . '/resources/vues'));
$pageBonjour = $aiguillageWeb->servir(new Requete('GET', '/bonjour'));
$jetonTrouve = preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $pageBonjour->corps(), $correspondanceJeton) === 1;
$verifier($pageBonjour->code() === 200 && $jetonTrouve, 'GET integre rend la page et son jeton CSRF');

$reponsePost = $aiguillageWeb->servir(new Requete('POST', '/bonjour', [
    '_csrf' => $correspondanceJeton[1],
    'nom' => 'Awa',
]));
$verifier(
    $reponsePost instanceof ReponseRedirection
        && $reponsePost->code() === 303
        && $reponsePost->entetes()['Location'] === '/bonjour',
    'POST valide du formulaire redirige explicitement en 303'
);
$pageApresPost = $aiguillageWeb->servir(new Requete('GET', '/bonjour'));
$verifier(
    strpos($pageApresPost->corps(), 'Le formulaire a bien ete envoye.') !== false,
    'GET apres redirection affiche le message flash'
);
$pageSuivante = $aiguillageWeb->servir(new Requete('GET', '/bonjour'));
$verifier(
    strpos($pageSuivante->corps(), 'Le formulaire a bien ete envoye.') === false,
    'le message flash disparait apres sa premiere page'
);

$formulaireInvalide = $aiguillageWeb->servir(new Requete('POST', '/bonjour', [
    '_csrf' => $correspondanceJeton[1],
]));
$verifier(
    $formulaireInvalide instanceof ReponseHtml
        && $formulaireInvalide->code() === 422
        && strpos($formulaireInvalide->corps(), 'Veuillez saisir un nom valide') !== false,
    'formulaire invalide reste affiche avec le statut 422 et son message'
);

// Le groupe d'exemple utilise le générateur pour son action de formulaire et hérite de CSRF.
$pageAdmin = $aiguillageWeb->servir(new Requete('GET', '/admin'));
$jetonAdminTrouve = preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $pageAdmin->corps(), $correspondanceJetonAdmin) === 1;
$actionAdminTrouvee = preg_match('/<form method="post" action="([^"]+)"/', $pageAdmin->corps(), $correspondanceActionAdmin) === 1;
$reponseAdmin = $aiguillageWeb->servir(new Requete('POST', '/admin/verification', [
    '_csrf' => $jetonAdminTrouve ? $correspondanceJetonAdmin[1] : '',
]));
$verifier(
    $actionAdminTrouvee
        && $correspondanceActionAdmin[1] === '/admin/verification'
        && $reponseAdmin->code() === 200
        && isset($pageAdmin->entetes()['X-Demo-Duree-Ms'])
        && isset($reponseAdmin->entetes()['X-Demo-Duree-Ms'])
        && $reponseAdmin->corps() === '<h1>Jeton CSRF valide</h1>',
    'exemple admin genere son URL nommee, protege le POST et applique son middleware'
);

$nombreConstructionsAvantActif = $constructionsRouteur;
$reponseCss = $aiguillageWeb->servir(new Requete('GET', '/assets/vues/bonjour/style.css'));
$verifier(
    $reponseCss->code() === 200
        && strpos(strtolower($reponseCss->entetes()['Content-Type']), 'text/css') === 0
        && $constructionsRouteur === $nombreConstructionsAvantActif,
    'actif CSS servi sans construire le routeur'
);
$reponseHeadCss = $aiguillageWeb->servir(new Requete('HEAD', '/assets/vues/bonjour/style.css'));
$verifier(
    $reponseHeadCss->code() === 200 && $reponseHeadCss->corps() === '',
    'HEAD actif renvoie les entetes sans corps'
);
$actifHorsRacine = $aiguillageWeb->servir(new Requete('GET', '/assets/vues/bonjour/../secret.php'));
$verifier($actifHorsRacine->code() === 404, 'un chemin actif hors convention est introuvable');

// La frontiere HTTP masque les details internes mais les remet au journaliseur.
$erreurJournalisee = null;
$aiguillageErreur = new AiguillageWeb(
    function () {
        throw new RuntimeException('detail interne secret');
    },
    function (Throwable $erreur) use (&$erreurJournalisee) {
        $erreurJournalisee = $erreur;
    }
);
$reponseErreur = $aiguillageErreur->servir(new Requete('GET', '/erreur'));
$verifier(
    $reponseErreur->code() === 500
        && strpos($reponseErreur->corps(), 'detail interne secret') === false
        && $erreurJournalisee instanceof Throwable,
    'erreur interne journalisee et detail masque au visiteur'
);
$contexteErreur500 = null;
$aiguillageErreurPersonnalisee = new AiguillageWeb(
    function () {
        throw new RuntimeException('secret a ne pas exposer');
    },
    null,
    null,
    function (int $code, array $contexte) use (&$contexteErreur500): ReponseHtml {
        $contexteErreur500 = ['code' => $code, 'donnees' => $contexte];
        return new ReponseHtml($code, 'Page personnalisee');
    }
);
$reponse500Personnalisee = $aiguillageErreurPersonnalisee->servir(new Requete('GET', '/erreur'));
$verifier(
    $reponse500Personnalisee->code() === 500
        && $reponse500Personnalisee->corps() === 'Page personnalisee'
        && $contexteErreur500 === [
            'code' => 500,
            'donnees' => ['message' => 'Une erreur inattendue est survenue.'],
        ],
    'page 500 personnalisable sans transmettre le detail de l exception'
);

echo PHP_EOL . 'Tests : ' . $nombreTests . ' ; echecs : ' . count($echecs) . PHP_EOL;
exit($echecs === [] ? 0 : 1);
