# Philosophie et architecture de php-clean-code-web

`php-clean-code-web` est l'extension de présentation web du socle `phpCleanCode`. Sa direction est volontairement **légère, orientée Clean Architecture et pédagogique** : montrer les responsabilités et les échanges entre composants plutôt que les cacher derrière un mécanisme opaque.

## Ce que le paquet prend en charge

- `Http/` reçoit les requêtes de l'application, les route et applique les contrôles web communs.
- `Presentation/` représente les réponses HTTP, HTML et les redirections.
- `Vue/` rend les vues et leurs layouts.
- `Validation/` valide les données soumises par les formulaires.
- `Application/Port/StockageSession` définit le contrat de session; `Infrastructure/SessionPhp` en fournit un adaptateur PHP natif.

Les contrôleurs, les règles métier et les cas d'usage appartiennent à l'application qui utilise le paquet. Ils ne sont pas intégrés au framework, car ils varient d'un projet à l'autre.

## Les dépendances restent visibles

L'application construit ses contrôleurs et leur fournit leurs dépendances. La table de routes référence ensuite les méthodes, par exemple `[$bonjourControleur, 'saluer']`. Le routeur appelle cette méthode, mais ne construit pas le contrôleur et ne cherche pas de service dans un conteneur global.

Ce choix réduit la magie et rend le câblage plus explicite. Il peut demander quelques lignes de configuration supplémentaires; en échange, le lecteur peut suivre les dépendances et remplacer leurs implémentations.

## Le middleware comme adaptateur HTTP

Un middleware de route appartient à la frontière HTTP : c'est un **adaptateur** entre la requête web et l'application. Il peut lire les informations de transport (en-têtes, session, paramètres), appeler un cas d'usage ou un port fourni par l'application, puis décider de continuer avec `$suite()` ou de retourner une réponse HTTP.

Par exemple, un middleware d'authentification peut demander à un service applicatif quel utilisateur correspond à la session ou au jeton présenté. Il peut arrêter la requête si aucun utilisateur n'est identifié. Il ne doit pas ouvrir lui-même une connexion à la base de données, ni porter les règles métier qui déterminent les droits de cet utilisateur. Ces règles restent dans l'application ou le domaine, selon leur responsabilité.

L'application construit le middleware avec ses dépendances, puis le déclare sur une route. Le routeur se limite à exécuter le callable; il ne crée pas ses dépendances et ne donne pas accès à un conteneur global. L'authentification pendant une requête (retrouver l'identité) reste également distincte du cas d'usage de connexion (vérifier des identifiants et établir une session). La protection CSRF traite encore un autre risque et reste un contrôle séparé.

```text
Requête HTTP
  → middleware (adaptateur HTTP)
      → cas d'usage / port applicatif
      → poursuivre avec $suite() ou retourner une réponse HTTP
  → contrôleur et cas d'usage de la route
```

Le middleware de démonstration `exemples/middlewares/MesurerTempsMiddleware.php` mesure le temps d'exécution et ajoute un en-tête. La table de routes le déclare comme objet invokable, construit par l'application. Il illustre l'enveloppe HTTP; il ne représente pas une implémentation d'authentification.

`CorsMiddleware` est aussi un adaptateur HTTP, placé autour du traitement dans `AiguillageWeb`. Il lit l'origine et les métadonnées du précontrôle, puis ajoute les en-têtes CORS autorisés à la réponse du routeur. La politique (origines, méthodes, en-têtes et identifiants) est fournie par l'application. Ce mécanisme ne remplace ni l'authentification, ni l'autorisation métier, ni CSRF.

Les en-têtes de sécurité sont aussi appliqués à la frontière HTTP par EnTetesSecuriteMiddleware. Les protections de base sont configurables ; CSP et HSTS restent explicites car leur pertinence dépend du contenu et du déploiement HTTPS.

## Parcours d'une page

1. Le point d'entrée construit la requête à partir des données HTTP.
2. `AiguillageWeb` donne d'abord la possibilité à `ServeurActifsVue` de servir un CSS ou un JavaScript.
3. Pour une page, `RouteurWeb` trouve la route et appelle l'action déclarée.
4. Le contrôleur utilise `MoteurVue` et retourne une réponse complète.
5. Le point d'entrée envoie la réponse au navigateur.

Un formulaire HTML réussi peut choisir explicitement une `ReponseRedirection` et un message temporaire via `MessagesFlash`. Une action AJAX reste libre de retourner une réponse JSON, sans redirection automatique.

Les réponses `403`, `404`, `405` et `500` ont des pages génériques sûres par défaut. L'application peut injecter une fabrique de réponses dans `RouteurWeb` et `AiguillageWeb` pour les adapter. Elle reçoit uniquement le statut et un contexte destiné à l'affichage ; l'exception technique d'une erreur `500` ne lui est pas transmise.

## Comment apprendre avec le code

Commencez par le [README du paquet](README.md), puis suivez ce parcours :

1. `exemples/public/index.php` : composition des dépendances et point d'entrée.
2. `exemples/routes.php` et `exemples/controleurs/BonjourControleur.php` : table de routes et actions.
3. `src/Http/RouteurWeb.php` et `src/Http/AiguillageWeb.php` : dispatch HTTP.
4. `src/Vue/MoteurVue.php` et `src/Presentation/` : rendu et réponses.
5. `src/Http/GestionnaireCsrf.php`, `src/Infrastructure/SessionPhp.php` : sécurité web et adaptateur de session.

La suite autonome `tests/run.php` montre comment vérifier les contrats de routage et de réponse, l'échappement des vues, l'assemblage avec un layout, les cas d'erreur et le parcours de l'exemple sans démarrer un serveur HTTP.
