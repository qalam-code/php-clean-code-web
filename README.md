# php-clean-code-web

Extension web de `qalam-code/php-clean-code`, construite autour d'un rendu HTML et de composants de vues.

## Philosophie

`php-clean-code-web` prolonge un socle **léger, orienté Clean Architecture et pédagogique**. Il rend visibles le routage, les contrôleurs, les vues, les sessions et la protection CSRF au lieu de masquer leur fonctionnement derrière un grand nombre de conventions implicites.

Le paquet web fournit des mécanismes de présentation ; le domaine et les cas d'usage restent dans l'application, et les dépendances sont assemblées explicitement. Cela demande parfois davantage de câblage qu'un framework full-stack, mais aide à comprendre où se trouve chaque responsabilité et comment la remplacer.

Le [guide d'architecture Web](ARCHITECTURE.md) complète ce README avec le rôle des composants et le parcours d'une requête. Le README de `phpCleanCode` renvoie à son chapitre détaillé sur les principes du socle API.

## Feuille de route

Le framework fournit déjà une base web pédagogique : routes, contrôleurs, cas d’usage, vues, sessions, protection CSRF, middleware et validation simple. Les évolutions ci-dessous sont des pistes ordonnées pour élargir les types d’applications réalisables. Elles ne sont pas encore toutes disponibles dans la version actuelle et chaque application pourra choisir ses propres adaptateurs.

1. **Persistance et migrations de base de données** — définir des ports adaptés aux besoins des cas d’usage, puis proposer un adaptateur concret, par exemple avec PDO. Cela permettra de conserver et retrouver les données sans faire dépendre le domaine d’un moteur de base de données. Les migrations aideront à faire évoluer le schéma de données de manière reproductible.
2. **Authentification et autorisations complètes** — documenter et compléter le parcours d’inscription, de connexion, de déconnexion et de récupération d’identité. L’adaptateur HTTP pourra protéger une route par middleware ; les règles d’autorisation métier resteront dans les cas d’usage ou le domaine. Cela donnera un exemple sûr sans transformer le middleware en couche métier.
3. **Configuration et variables d’environnement** — proposer une manière cohérente de charger et valider les paramètres d’une application selon son environnement. Cela évitera de coder en dur les secrets et les paramètres propres au déploiement, tout en gardant la configuration explicite.
4. **Entrées HTTP avancées** — traiter les fichiers téléversés et les autres formes de requête web avec des objets adaptés, puis les convertir en données utilisables par l’application. Cela permettra de construire des formulaires plus complets tout en isolant les détails HTTP.
5. **Tests d’intégration et exemples d’application** — compléter les tests unitaires par des tests couvrant le câblage réel, les adaptateurs et les échanges HTTP, puis maintenir un exemple d’application représentatif. Cela aidera les utilisateurs à valider leurs choix d’architecture et à repérer les régressions.
6. **Préparation à une version stable 1.0** — préciser les contrats publics, les limites de compatibilité PHP, les consignes de déploiement et les règles de versionnement. Une version 1.0 sera pertinente quand les parcours principaux seront documentés, testés sur les versions PHP annoncées et accompagnés d’une procédure de mise à jour claire.

Cette feuille de route décrit une progression possible, pas une liste de dépendances obligatoires. Le principe reste de garder le cœur petit et de fournir des ports et adaptateurs que l’application peut remplacer.

## Installation avec Composer

En attendant la publication sur Packagist, l'application consommatrice doit déclarer les deux dépôts GitHub VCS dans son propre projet. Lancez ces commandes depuis le dossier de l'application :

    composer config repositories.qalam-core vcs https://github.com/qalam-code/phpCleanCode
    composer config repositories.qalam-web vcs https://github.com/qalam-code/php-clean-code-web
    composer require qalam-code/php-clean-code-web:^0.1

La première déclaration permet à Composer de résoudre la dépendance sur le socle API. La contrainte ^0.1 installe une version publiée compatible de la série 0.1. Après publication sur Packagist, l'installation pourra se réduire à composer require qalam-code/php-clean-code-web.

## Composants de vues

Chaque composant reste dans un dossier hors du répertoire public :

```text
resources/vues/bonjour/
  vue.html
  style.css
  script.js
resources/vues/layouts/principal.php
resources/vues/partiels/
```

`MoteurVue::rendreAvecLayout('bonjour', ...)` rend `vue.html` et associe ses actifs. `ServeurActifsVue` sert uniquement `style.css` et `script.js` par le point d entree web ; les sources des vues ne sont pas exposées. Les marqueurs `{{ nom }}` dans le HTML sont remplacés par des valeurs échappées.

## Fichiers téléversés

Les actions de route reçoivent la `Requete` du socle API. Pour un formulaire `multipart/form-data`, l’application peut lire les métadonnées avec `$requete->fichier('document')` ou `$requete->fichiers()`. Les champs multiples comme `documents[]` sont représentés par des tableaux de `FichierTeleverse`.

Le nom original et le type MIME annoncés par le navigateur ne sont pas fiables. Le framework fournit le code d’erreur PHP et le chemin temporaire, mais ne stocke pas le fichier. L’application doit vérifier le contenu, appliquer ses limites et confier le stockage à un adaptateur d’infrastructure.
## Protection CSRF

Les méthodes `POST`, `PUT`, `PATCH` et `DELETE` sont protégées par défaut. Une route peut choisir explicitement `csrf => true` ou `csrf => false`. La route POST Bonjour active la protection ; le formulaire contient un jeton lié à la session. `GestionnaireCsrf` accepte aussi un en-tête AJAX configurable.

Le port `StockageSession` permet de remplacer le stockage. `SessionPhp` fournit l’adaptateur PHP natif, active le mode strict et un cookie HttpOnly ; il choisit Secure selon HTTPS. SameSite=Lax est ajouté à partir de PHP 7.3. Le jeton synchronisé reste le contrôle CSRF principal.

## Actions des routes

Une action peut etre une fermeture ou une methode d'un controleur, sous la forme `[$controleur, 'methode']`. L'application construit le controleur et lui fournit ses dependances avant de declarer les routes ; le routeur appelle ensuite la methode avec la requete et les parametres du chemin.

Pour partager un préfixe, une option CSRF et des middleware, `GroupeRoutes` transforme une liste de routes en routes ordinaires. Les middleware du groupe sont exécutés avant ceux propres à la route. Une route peut remplacer explicitement l'option CSRF commune :

```php
$routesAdmin = GroupeRoutes::avecPrefixe('/admin', [
    [
        'chemin' => '/articles',
        'methodes' => ['POST'],
        'action' => [$controleur, 'creerArticle'],
    ],
    [
        'chemin' => '/sante',
        'methodes' => ['GET'],
        'csrf' => false,
        'action' => [$controleur, 'verifierSante'],
    ],
], [
    'csrf' => true,
    'middleware' => [$mesurerTemps],
]);
```

La première route devient `/admin/articles` et hérite de `csrf => true`. La seconde devient `/admin/sante` et conserve son propre réglage. Les deux héritent du middleware de mesure. Le résultat peut être fusionné avec d'autres routes avant de le transmettre à `RouteurWeb`.

L'exemple exécutable déclare également un petit groupe `/admin` dans `exemples/routes.php`. Ouvrez `/admin` puis envoyez le formulaire : la route POST `/admin/verification` hérite de la protection CSRF du groupe. La route GET `/admin` désactive explicitement CSRF, car l'option de groupe s'applique à chaque route qui ne la remplace pas.

Une route peut recevoir un `nom`. `GenerateurUrl::pour()` construit alors son chemin et encode les paramètres dynamiques comme des segments. Il applique également les contraintes de la route et refuse de générer une URL avec une valeur invalide. Les paramètres de query string se passent séparément en troisième argument et sont encodés selon RFC 3986 :

```php
$url = $generateurUrl->pour('article.detail', ['id' => 42], ['page' => 2, 'recherche' => 'a b']);
// /articles/42?page=2&recherche=a%20b
```

Dans l'exemple, le formulaire admin obtient son URL depuis le nom `admin.verification`, et le contrôleur utilise `bonjour.index` pour construire sa redirection après le POST. Cela évite de recopier ces chemins dans le HTML et le contrôleur.

Les paramètres peuvent aussi être limités par une expression régulière, sans delimiters PCRE :

```php
[
    'chemin' => '/articles/{id}',
    'methodes' => ['GET'],
    'contraintes' => ['id' => '[0-9]+'],
    'action' => [$controleur, 'afficher'],
]
```

Ici, la route n'accepte que des identifiants numériques. Un segment qui ne respecte pas la contrainte est traité comme une route introuvable (`404`), et l'action n'est pas appelée. Les contraintes ne remplacent pas la validation métier du cas d'usage.

Une route déclarée pour `GET` répond aussi à `HEAD` : le routeur exécute l'action afin d'obtenir le statut et les en-têtes, puis retire le corps avant de retourner la réponse. `HEAD` est également annoncé dans l'en-tête `Allow` d'une réponse `405` lorsqu'une route GET correspond au chemin.

Pour un chemin connu, le routeur répond automatiquement à `OPTIONS` avec le statut `204` et les méthodes disponibles dans `Allow`. Une route `OPTIONS` déclarée explicitement par l'application garde priorité. Cette réponse décrit les méthodes HTTP disponibles ; elle ne constitue pas à elle seule une configuration CORS pour les navigateurs.

## Middleware de route

Une route peut déclarer une liste de middleware sous forme de fermetures ou d'objets invokables (`__invoke`). Chaque middleware reçoit la requête, les paramètres du chemin et une fonction `$suite`. Il peut appeler cette fonction pour continuer la chaîne, puis examiner la réponse, ou retourner directement une réponse (par exemple 403) pour interrompre le traitement. L'ordre du tableau est l'ordre d'entrée ; les réponses reviennent dans l'ordre inverse. Chaque middleware doit retourner une `ReponseWeb` et ne peut poursuivre la chaîne qu'une seule fois.

```php
'middleware' => [
    function ($requete, array $parametres, callable $suite) {
        if (!$utilisateurEstConnecte) {
            return new ReponseHtml(403, '<h1>Acces refuse</h1>');
        }

        return $suite();
    },
],
```

Dans cette architecture, le middleware est un **adaptateur HTTP** : il traduit la requête entrante en appel vers l'application, puis traduit le résultat en réponse HTTP. Il peut vérifier la présence d'une identité ou arrêter la requête, mais ne doit pas interroger directement une base de données ni contenir les règles métier. Pour connaître l'utilisateur courant ou vérifier une permission métier, il reçoit explicitement un cas d'usage ou un port de l'application, construit par l'application et injecté dans le middleware. Le routeur ne lui fournit pas de conteneur global.

Ainsi, un middleware d'authentification peut demander à l'application quel utilisateur correspond à la session ou au jeton, puis refuser la requête ou appeler `$suite()`. La vérification des identifiants lors de la connexion reste un cas d'usage ; le middleware ne remplace ni ce cas d'usage ni les règles d'autorisation du domaine.

Dans l'exemple, `exemples/middlewares/MesurerTempsMiddleware.php` définit un objet invokable que le groupe `/admin` utilise pour ajouter l'en-tête `X-Demo-Duree-Ms` après chaque action. L'application crée l'objet puis le passe au groupe ; si un middleware avait des dépendances, elles pourraient lui être fournies par le constructeur. Cet exemple sert à observer le mécanisme ; il ne remplace pas une mesure de performance de production.

## CORS

`CorsMiddleware` configure le partage des réponses avec des origines de navigateur précises. Il est facultatif et s'ajoute à `AiguillageWeb` comme dernier argument :

```php
$cors = new CorsMiddleware(
    ['http://localhost:3000'],       // origines autorisées
    ['GET', 'POST'],                 // méthodes autorisées pour CORS
    ['Content-Type', 'X-CSRF-Token'], // en-têtes acceptés au précontrôle
    true,                            // autoriser les cookies/identifiants
    600,                             // durée de cache du précontrôle, en secondes
    ['X-Demo-Duree-Ms']              // en-têtes de réponse lisibles par le navigateur
);

$aiguillage = new AiguillageWeb($fabriqueRouteur, null, $serveurActifs, null, $cors);
```

Avec les identifiants activés, une origine exacte est obligatoire : la configuration refuse `'*'`. Le middleware traite les réponses ordinaires et les précontrôles `OPTIONS`; il n'ajoute les en-têtes d'autorisation qu'aux origines, méthodes et en-têtes admis. L'exemple exécutable utilise `http://localhost:3000` comme origine de développement : adaptez-la à votre frontend.

CORS est un contrôle de partage appliqué par les navigateurs. Il n'authentifie pas l'appelant, ne bloque pas les clients non-navigateurs et ne remplace pas CSRF. La réponse `OPTIONS` automatique du routeur annonce les méthodes HTTP (`Allow`), tandis que le middleware ajoute les en-têtes CORS spécifiques (`Access-Control-Allow-*`).

## En-têtes de sécurité HTTP

AiguillageWeb ajoute par défaut quelques en-têtes de protection à toutes les réponses, y compris les erreurs et les fichiers CSS/JS :

- X-Content-Type-Options: nosniff évite que le navigateur devine un type de contenu différent de celui annoncé.
- X-Frame-Options: DENY interdit l'affichage du site dans un cadre.
- Referrer-Policy: strict-origin-when-cross-origin limite les informations d'URL transmises comme référent.
- Permissions-Policy désactive par défaut la caméra, le microphone et la géolocalisation.

Les valeurs peuvent être remplacées dans EnTetesSecuriteMiddleware. Une valeur null retire un en-tête de base. La Content Security Policy (CSP) et HSTS ne sont pas activées par défaut : leur configuration dépend des scripts, styles et du déploiement HTTPS de l'application.

Par exemple, vous pouvez personnaliser la politique ainsi :

    $securite = new EnTetesSecuriteMiddleware([
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "default-src 'self'",
        'Strict-Transport-Security' => 'max-age=31536000',
    ]);
    $aiguillage = new AiguillageWeb($fabriqueRouteur, null, $serveurActifs, null, $cors, $securite);

Passez null pour retirer un en-tête par défaut, ou false comme sixième argument de AiguillageWeb pour désactiver tout le mécanisme.

N'activez HSTS que lorsque l'application est effectivement servie en HTTPS et configurez la CSP selon les ressources réellement utilisées.

## Validation des formulaires

`ValidateurDonnees` applique des regles reutilisables a un tableau de donnees. Les regles disponibles sont `required`, `string`, `trim`, `min`, `max`, `email` et `callback`. `ResultatValidation` expose les donnees normalisees, les erreurs par champ, `estValide()` et `premiereErreur()`. Les messages peuvent etre adaptes champ par champ.

```php
$resultat = $validateur->valider($requete->corps(), [
    'nom' => ['required' => true, 'string' => true, 'trim' => true, 'max' => 100],
], [
    'nom' => 'Saisissez un nom de 1 a 100 caracteres.',
]);

if (!$resultat->estValide()) {
    $erreurs = $resultat->erreurs();
}
```

L'exemple `/bonjour` utilise ce validateur et reaffiche les erreurs avec le statut HTTP 422. Les messages sont echappes par le moteur de vues HTML.

## Formulaires HTML et redirection

Une action de formulaire HTML peut retourner `ReponseRedirection`, qui envoie une redirection HTTP `303` vers une URL locale choisie par le controleur. Le navigateur effectue alors un `GET`, ce qui evite de renvoyer le formulaire lors d'un rafraichissement. `MessagesFlash` conserve un message en session jusqu'a sa prochaine lecture, puis l'efface. Les erreurs HTML de validation, comme le statut 422, restent affichees directement. Cette redirection est explicite dans le controleur HTML ; les actions AJAX qui retournent du JSON ne sont pas redirigees.

## Exemple

```sh
composer install
php -S 127.0.0.1:8000 -t exemples/public
```

Ouvrez `http://127.0.0.1:8000/bonjour` puis soumettez le formulaire. Une saisie invalide retourne 422 ; un jeton CSRF absent ou incorrect retourne 403 avant l appel de l action.

## Tests

La suite autonome couvre le routage, les réponses 404/405/500/422, la validation CSRF, les redirections locales, les messages flash, l'échappement HTML et le parcours complet de l'exemple (requête, contrôleur, vue, redirection). Elle vérifie aussi le service des actifs CSS, la méthode `HEAD` et le refus des chemins hors convention. Elle ne démarre pas de serveur HTTP et ne nécessite pas PHPUnit :

```sh
composer test
```

## Personnaliser les réponses d'erreur

`RouteurWeb` et `AiguillageWeb` acceptent la même fabrique facultative de réponses. Elle reçoit le statut HTTP et un contexte public, puis doit retourner une `ReponseWeb` avec ce statut. Le routeur l'utilise pour `403`, `404` et `405` ; l'aiguillage l'utilise pour `500`. Pour une erreur interne, l'exception technique est journalisée, mais seule une phrase générique est transmise à la fabrique. Sans fabrique, les réponses HTML génériques du framework sont conservées.
