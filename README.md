# php-clean-code-web

Extension web de `qalam-code/php-clean-code`, construite autour d'un rendu HTML et de vues PHP.

## Installation pour le developpement

Depuis la racine de ce depot :

```sh
composer install
```

Le paquet API doit etre disponible dans Packagist, ou configure comme depot VCS dans Composer. Le code web utilise sa classe `PhpCleanCode\\Http\\Requete` pour recevoir la requete HTTP.

## Premiere route HTML

L'exemple expose `GET /bonjour` avec `?nom=Amadou`, ainsi que `GET /bonjour/{nom}` :

```sh
php -S 127.0.0.1:8000 -t exemples/public
```

Ouvrez `http://127.0.0.1:8000/bonjour?nom=Amadou` ou `http://127.0.0.1:8000/bonjour/Amadou`.

Le point d'entree charge les routes depuis `exemples/routes.php`, le routeur appelle l'action, et le moteur rend `exemples/vues/bonjour.php`. Les valeurs dynamiques doivent etre affichees avec `$this->echapper(...)`.

Comportement actuel volontairement limite : chemins statiques ou avec parametres simples, methode HTTP et vues PHP simples. Les formulaires, layouts et gestion centralisee des erreurs viendront apres validation de cette base.