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

`MoteurVue::rendreAvecLayout()` place la vue de la page dans un layout partage. Le layout peut composer des vues partielles avec `MoteurVue::rendre()`. Les valeurs dynamiques doivent etre affichees avec `$this->echapper(...)`; le contenu HTML de la vue interne est insere par le layout comme contenu genere par l application.

Comportement actuel volontairement limite : chemins statiques ou avec parametres simples, methodes HTTP, formulaire HTML simple, vues et layouts PHP. La validation des champs reste une responsabilite de l application.