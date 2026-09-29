# php-clean-code-web

Extension web de `qalam-code/php-clean-code`, construite autour d'un rendu HTML et de vues PHP.

## Installation pour le developpement

Depuis la racine de ce depot :

```sh
composer install
```

Le paquet API doit etre disponible dans Packagist, ou configure comme depot VCS dans Composer. Le routeur web reutilise `PhpCleanCode\\Http\\Requete`, qui expose methode, chemin, entetes, query string et donnees du corps, y compris les formulaires classiques.

## Composants de vues

Chaque composant est garde dans un repertoire unique, hors du repertoire public :

```text
resources/vues/bonjour/
  vue.html
  style.css
  script.js
```

`MoteurVue::rendreAvecLayout('bonjour', ...)` rend `vue.html`, puis associe automatiquement les actifs. `ServeurActifsVue` les sert par les URL `/assets/vues/bonjour/style.css` et `/assets/vues/bonjour/script.js`. Le serveur ne permet pas de telecharger `vue.html` ni d autres fichiers du repertoire. Les variables HTML utilisent `{{ nom }}` et sont echappees automatiquement.

## Exemple

```sh
php -S 127.0.0.1:8000 -t exemples/public
```

Ouvrez `http://127.0.0.1:8000/bonjour/Amadou` ou `http://127.0.0.1:8000/bonjour`, puis soumettez le formulaire. Un nom vide ou de plus de 100 caracteres est refuse avec le statut HTTP 422.

Le CSS et le JavaScript restent standards et ne sont pas automatiquement encapsules comme dans Angular. Le layout ajoute une classe telle que `vue-bonjour` au `body` pour aider a limiter les selecteurs.