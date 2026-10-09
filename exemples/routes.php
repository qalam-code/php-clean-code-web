<?php
declare(strict_types=1);

require_once __DIR__ . '/controleurs/BonjourControleur.php';
require_once __DIR__ . '/middlewares/MesurerTempsMiddleware.php';

use QalamCode\PhpCleanCodeWeb\Http\GestionnaireCsrf;
use QalamCode\PhpCleanCodeWeb\Http\GenerateurUrl;
use QalamCode\PhpCleanCodeWeb\Http\GroupeRoutes;
use QalamCode\PhpCleanCodeWeb\Exemples\Middleware\MesurerTempsMiddleware;
use QalamCode\PhpCleanCodeWeb\Presentation\ReponseHtml;
use QalamCode\PhpCleanCodeWeb\Validation\ValidateurDonnees;
use QalamCode\PhpCleanCodeWeb\Vue\MoteurVue;
use QalamCode\PhpCleanCodeWeb\Exemples\Controleur\BonjourControleur;
use QalamCode\PhpCleanCodeWeb\Application\MessagesFlash;

return function (
    MoteurVue $vues,
    GestionnaireCsrf $csrf,
    ValidateurDonnees $validateur,
    MessagesFlash $messagesFlash
): array {
    // Le contrôleur est construit ici avec ses dépendances, puis ses méthodes
    // sont données au routeur comme callables PHP.

    $generateurUrls = null;
    // La table des routes doit être complète avant de construire le générateur.
    // Cette fonction est injectée au contrôleur et appelée seulement pendant une requête.
    $genererUrl = function (string $nom, array $parametres = []) use (&$generateurUrls): string {
        if (!$generateurUrls instanceof GenerateurUrl) {
            throw new LogicException('Le generateur URL doit etre compose avant le traitement des requetes.');
        }
        return $generateurUrls->pour($nom, $parametres);
    };
    $bonjourControleur = new BonjourControleur($vues, $csrf, $validateur, $messagesFlash, $genererUrl);

    // Un objet avec __invoke() est callable et peut lui aussi être déclaré comme middleware.
    $mesurerTemps = new MesurerTempsMiddleware();

    $routesBonjour = [
        [
            'nom' => 'bonjour.index',
            'chemin' => '/bonjour',
            'methodes' => ['GET'],
            'action' => [$bonjourControleur, 'saluer'],
        ],
        [
            'nom' => 'bonjour.formulaire',
            'chemin' => '/bonjour',
            'methodes' => ['POST'],
            'csrf' => true,
            'action' => [$bonjourControleur, 'traiterFormulaire'],
        ],
        [
            'nom' => 'bonjour.saluer',
            'chemin' => '/bonjour/{nom}',
            'methodes' => ['GET'],
            'action' => [$bonjourControleur, 'saluer'],
        ],
    ];

    // Ce groupe montre un espace /admin avec un préfixe, un middleware commun
    // et CSRF actif par défaut. L'écran GET désactive CSRF explicitement.
    $routesAdmin = GroupeRoutes::avecPrefixe('/admin', [
        [
            'nom' => 'admin.index',
            'chemin' => '/',
            'methodes' => ['GET'],
            'csrf' => false,
            'action' => function () use ($csrf, $genererUrl): ReponseHtml {
                $champ = htmlspecialchars($csrf->cleFormulaire(), ENT_QUOTES, 'UTF-8');
                $jeton = htmlspecialchars($csrf->jeton(), ENT_QUOTES, 'UTF-8');
                $action = htmlspecialchars($genererUrl('admin.verification'), ENT_QUOTES, 'UTF-8');
                return new ReponseHtml(200,
                    '<h1>Espace admin</h1>'
                    . '<form method="post" action="' . $action . '">'
                    . '<input type="hidden" name="' . $champ . '" value="' . $jeton . '">'
                    . '<button type="submit">Verifier le jeton CSRF</button>'
                    . '</form>'
                );
            },
        ],
        [
            'nom' => 'admin.verification',
            'chemin' => '/verification',
            'methodes' => ['POST'],
            'action' => function (): ReponseHtml {
                return new ReponseHtml(200, '<h1>Jeton CSRF valide</h1>');
            },
        ],
    ], [
        'csrf' => true,
        'middleware' => [$mesurerTemps],
    ]);

    $routes = array_merge($routesBonjour, $routesAdmin);
    // Les noms sont indexés après le développement des groupes, donc le générateur connait les chemins finaux.
    $generateurUrls = new GenerateurUrl($routes);

    return $routes;
};
