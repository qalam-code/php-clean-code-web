# php-clean-code-web

Extension web de `qalam-code/php-clean-code`, construite autour d'un rendu HTML et de vues PHP.

## Installation pour le developpement

Depuis la racine de ce depot :

```sh
composer install
```

Le paquet API doit etre disponible dans Packagist, ou configure comme depot VCS dans Composer. Le routeur web reutilise `PhpCleanCode\\Http\\Requete`, qui expose methode, chemin, entetes, query string et donnees du corps, y compris les formulaires classiques. L action de route prepare ensuite les donnees destinees a la vue.

## Premiere route HTML

L'exemple expose `GET /bonjour`, `GET /bonjour/{nom}` et un formulaire `POST /bonjour` :

```sh
php -S 127.0.0.1:8000 -t exemples/public
```

Ouvrez `http://127.0.0.1:8000/bonjour/Amadou` ou `http://127.0.0.1:8000/bonjour`, puis soumettez le formulaire. Un nom vide ou de plus de 100 caracteres est refuse avec le statut HTTP 422 et un message affiche dans le formulaire.

Chaque vue rendue avec `MoteurVue::rendreAvecLayout()` charge ses actifs par convention : `bonjour.html` utilise `public/assets/vues/bonjour.css` et `public/assets/vues/bonjour.js`. Les variables HTML s ecrivent `{{ nom }}` et sont echappees automatiquement. Le layout produit les balises CSS et JavaScript. La classe `vue-bonjour` sur le corps de page permet de limiter les selecteurs CSS a cette vue. Dans une application, le chemin URL des actifs se configure avec le second argument de `MoteurVue`, par exemple `new MoteurVue($repertoireVues, '/mon-app/assets/vues')`.

Les vues `.html` utilisent des marqueurs de variables simples, automatiquement echappes. Les vues `.php` restent disponibles pour les vues qui ont besoin de logique ou de composition. Le CSS est charge uniquement sur la vue correspondante, mais reste du CSS navigateur classique ; la classe de page permet de limiter ses selecteurs.