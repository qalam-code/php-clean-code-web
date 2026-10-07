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

## Parcours d'une page

1. Le point d'entrée construit la requête à partir des données HTTP.
2. `AiguillageWeb` donne d'abord la possibilité à `ServeurActifsVue` de servir un CSS ou un JavaScript.
3. Pour une page, `RouteurWeb` trouve la route et appelle l'action déclarée.
4. Le contrôleur utilise `MoteurVue` et retourne une réponse complète.
5. Le point d'entrée envoie la réponse au navigateur.

Un formulaire HTML réussi peut choisir explicitement une `ReponseRedirection` et un message temporaire via `MessagesFlash`. Une action AJAX reste libre de retourner une réponse JSON, sans redirection automatique.

## Comment apprendre avec le code

Commencez par le [README du paquet](README.md), puis suivez ce parcours :

1. `exemples/public/index.php` : composition des dépendances et point d'entrée.
2. `exemples/routes.php` et `exemples/controleurs/BonjourControleur.php` : table de routes et actions.
3. `src/Http/RouteurWeb.php` et `src/Http/AiguillageWeb.php` : dispatch HTTP.
4. `src/Vue/MoteurVue.php` et `src/Presentation/` : rendu et réponses.
5. `src/Http/GestionnaireCsrf.php`, `src/Infrastructure/SessionPhp.php` : sécurité web et adaptateur de session.
