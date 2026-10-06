# php-clean-code-web

Extension web de `qalam-code/php-clean-code`, construite autour d'un rendu HTML et de composants de vues.

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

Apres un `POST`, si l'action retourne une `ReponseHtml` avec un statut de succes (`2xx`), `RouteurWeb` repond par une redirection HTTP `303` vers le meme chemin. Le navigateur effectue alors un `GET`, ce qui evite de renvoyer le formulaire lors d'un rafraichissement. Les reponses HTML d'erreur, comme le statut 422 de validation, restent affichees directement. Les autres types de reponse, notamment les reponses JSON utilisees par des appels AJAX, ne sont pas rediriges.

## Exemple

```sh
composer install
php -S 127.0.0.1:8000 -t exemples/public
```

Ouvrez `http://127.0.0.1:8000/bonjour` puis soumettez le formulaire. Une saisie invalide retourne 422 ; un jeton CSRF absent ou incorrect retourne 403 avant l appel de l action.
