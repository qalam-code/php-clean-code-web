# php-clean-code-web

Extension web de `qalam-code/php-clean-code`, construite autour d'un rendu HTML et de composants de vues.

## Philosophie

`php-clean-code-web` prolonge un socle **léger, orienté Clean Architecture et pédagogique**. Il rend visibles le routage, les contrôleurs, les vues, les sessions et la protection CSRF au lieu de masquer leur fonctionnement derrière un grand nombre de conventions implicites.

Le paquet web fournit des mécanismes de présentation ; le domaine et les cas d'usage restent dans l'application, et les dépendances sont assemblées explicitement. Cela demande parfois davantage de câblage qu'un framework full-stack, mais aide à comprendre où se trouve chaque responsabilité et comment la remplacer.

Le [guide d'architecture Web](ARCHITECTURE.md) complète ce README avec le rôle des composants et le parcours d'une requête. Le README de `phpCleanCode` renvoie à son chapitre détaillé sur les principes du socle API.

## Composants de vues

Chaque composant reste dans un dossier hors du répertoire public :

```text
resources/vues/bonjour/
  vue.html
  style.css
  script.js
```

`MoteurVue::rendreAvecLayout('bonjour', ...)` rend `vue.html` et associe ses actifs. `ServeurActifsVue` sert uniquement `style.css` et `script.js` par le point d entree web ; les sources des vues ne sont pas exposées. Les marqueurs `{{ nom }}` dans le HTML sont remplacés par des valeurs échappées.

## Protection CSRF

Les méthodes `POST`, `PUT`, `PATCH` et `DELETE` sont protégées par défaut. Une route peut choisir explicitement `csrf => true` ou `csrf => false`. La route POST Bonjour active la protection ; le formulaire contient un jeton lié à la session. `GestionnaireCsrf` accepte aussi un en-tête AJAX configurable.

Le port `StockageSession` permet de remplacer le stockage. `SessionPhp` fournit l’adaptateur PHP natif, active le mode strict et un cookie HttpOnly ; il choisit Secure selon HTTPS. SameSite=Lax est ajouté à partir de PHP 7.3. Le jeton synchronisé reste le contrôle CSRF principal.

## Actions des routes

Une action peut etre une fermeture ou une methode d'un controleur, sous la forme `[$controleur, 'methode']`. L'application construit le controleur et lui fournit ses dependances avant de declarer les routes ; le routeur appelle ensuite la methode avec la requete et les parametres du chemin.

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
