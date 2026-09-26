# 4. La couche Http

`src/Http/` — **trois fichiers**. L'entrée de l'application : lire la requête,
trouver la route, appeler, ne jamais laisser une exception s'échapper.

---

## 4.1 `Requete`

**Signature**

```php
final class Requete
{
    public function __construct(
        string $methode,
        string $chemin,
        array $corps = [],
        array $requeteUrl = [],
        array $entetes = []
    );
    public static function depuisGlobales(): self;

    public function methode(): string;                      // majuscules
    public function chemin(): string;
    public function corps(): array;
    public function parametre(string $cle, $defaut = null);  // corps, puis query
    public function manquants(array $cles): array;
    public function entete(string $nom): ?string;            // insensible à la casse
}
```

**Rôle** — La requête entrante, lue une fois pour toutes.

**Pourquoi** — Les superglobales sont lues dans `depuisGlobales()` **et nulle
part ailleurs**. C'est ce qui rend tout le reste testable : un test construit
une `Requete` avec les valeurs qu'il veut, sans toucher `$_POST` ni simuler
`php://input`.

```php
$requete = new Requete('GET', '/facture', [], ['numero' => 'F-2026-001']);
```

**Les données sont rendues brutes, sans échappement.** Échapper à l'entrée
corrompt ce qu'on enregistre et ne protège de rien : c'est à la **sortie** —
requête préparée pour SQL, `json_encode` pour la réponse — que l'échappement a
un sens, parce que la destination est alors connue.

**`parametre()` cherche d'abord dans le corps, puis dans la query string.** Un
endpoint accepte ainsi les deux formes sans que le contrôleur ait à s'en
soucier.

**`manquants()` traite les chaînes vides comme absentes** :

```php
$absents = $requete->manquants(['identifiant', 'mot_de_passe']);
if ($absents !== []) {
    throw ErreurMetier::parametreManquant(implode(', ', $absents));
}
```

Une valeur vide passée par erreur n'est pas une valeur. L'ordre du tableau rendu
est celui demandé, pour que le message d'erreur soit stable.

**`depuisGlobales()` gère trois cas particuliers** qui coûtent chacun une demi-
journée quand on ne les connaît pas :

1. **JSON dans `php://input`**, avec repli sur `$_POST` si le corps décodé est
   vide — un formulaire classique n'arrive pas par `php://input`.
2. **`getallheaders()` manque** sous certaines configurations (php-fpm avec
   nginx, CLI), et c'est précisément là qu'`Authorization` se perd. Repli sur un
   parcours de `$_SERVER['HTTP_*']`.
3. **Apache masque `Authorization`** quand `CGIPassAuth` est désactivé —
   configuration fréquente en mutualisé. La réécriture de `.htaccess` le remet
   dans `REDIRECT_HTTP_AUTHORIZATION`, que `depuisGlobales()` sait relire.

> **⚠** Sans le point 3, tous les endpoints authentifiés répondent « token
> introuvable » alors que le client envoie bien son jeton. La ligne
> correspondante du `.htaccess` du squelette n'est pas décorative.

---

## 4.2 `Routeur`

**Signature**

```php
final class Routeur
{
    public function __construct(string $base = '');
    public function ajouter(
        string $chemin,
        callable $action,         // (): callable — reçoit une Requete, rend une ReponseHttp
        callable $presentateur,   // (): PresentateurAbstrait
        array $methodes = []      // vide = toutes
    ): void;
    public function resoudre(string $chemin): ?array;
    public function chemins(): array;
}
```

**Rôle** — Table des routes. Ne fait que résoudre ; c'est `Aiguillage` qui
exécute.

Deux déclarations dont les chemins deviennent identiques après normalisation
sont refusées avec `InvalidArgumentException`, au lieu de remplacer
silencieusement la route précédente.

**Chaque route porte son propre présentateur**, et c'est la décision de
conception la plus importante de cette classe.

> **⚠** Avec un présentateur unique pour toute l'application, une panne survenue
> pendant le câblage d'un endpoint est rendue par le format d'un autre. Vu en
> production : une base injoignable répondait **« identifiants invalides »** à
> chaque appel, parce que le présentateur de secours était celui de la
> connexion. Les consommateurs ont cherché du côté de leurs identifiants pendant
> que le problème était ailleurs. Voir [`11-pieges.md`](11-pieges.md), piège
> n° 3.

**Les deux entrées sont des fabriques, pas des objets.** Rien n'est construit
pour les routes qui ne sont pas appelées. Sur sept endpoints, une requête n'en
câble qu'un.

**Normalisation des chemins** — `/api/Paye-Facture/` et `paye-facture` désignent
la même route :

- le préfixe d'installation (`$base`) est retiré seulement s'il correspond à un segment complet ;
- les barres de début et de fin sont supprimées ;
- la casse est ignorée — un consommateur qui écrit `/Login` recevrait sinon un
  404 incompréhensible.

**`chemins()`** rend l'inventaire des routes déclarées. Utile à un test
d'inventaire : *« la liste des endpoints est-elle toujours celle-ci ? »* — une
route ajoutée par inadvertance se voit alors immédiatement.

**Utilisation**

```php
$routeur = new Routeur('/mon-projet');

$routeur->ajouter(
    'facture',
    function () { return new ControleurFacture($this->consulterFacture(), new PresentateurFacture()); },
    function () { return new PresentateurFacture(); },
    ['GET', 'POST']
);
```

---

## 4.3 `Aiguillage`

**Signature**

```php
final class Aiguillage
{
    public function __construct(
        Routeur $routeur,
        PresentateurAbstrait $secours,
        ?callable $journaliseur = null   // (Throwable): void — NE DOIT PAS LEVER
    );
    public function servir(Requete $requete): ReponseHttp;
}
```

**Rôle** — Point d'entrée unique : une requête entre, une réponse sort.

**Aucune exception ne sort d'ici.** Le `catch (Throwable)` final n'est pas un
tapis sous lequel on pousse les erreurs : c'est la promesse qu'un consommateur
recevra **toujours** du JSON et un code HTTP, jamais une page blanche ni une
trace PHP révélant les chemins du serveur.

Ce que le `catch` ne doit pas devenir : un endroit où l'erreur disparaît. D'où
le journaliseur — ce qui n'est pas rendu au consommateur doit être écrit quelque
part, sinon l'incident est invisible.

**Déroulé de `servir()`**

| Étape | Situation | Réponse |
|---|---|---|
| 1 | chemin inconnu | `$secours->traduire(RESSOURCE_INTROUVABLE)` → 404 |
| 2 | méthode non autorisée | 405, avec l'en-tête `Allow` ; le présentateur de la route peut adapter le corps |
| 3 | le présentateur de la route échoue à se construire | `$secours->panne()` → 500 |
| 4 | `ErreurMetier` levée par le contrôleur ou le cas d'usage | `$presentateur->traduire($e)` |
| 5 | n'importe quel autre `Throwable` | journalisé, puis `$presentateur->panne()` |

**La construction du contrôleur est dans le `try`, délibérément.** Un câblage qui
échoue — base injoignable, configuration absente — est un incident comme un
autre, et doit être rendu par le présentateur de la route appelée.

**Le présentateur, lui, est construit avant le `try`** : sans lui, rien ne peut
être rendu correctement. S'il échoue lui-même, le secours prend le relais — il
n'a, lui, aucune dépendance.

**Le journaliseur est protégé** par son propre `try` : un journal qui tombe ne
doit pas emporter la réponse avec lui.

**Utilisation** — c'est tout `public/index.php` :

```php
$requete = Requete::depuisGlobales();

$aiguillage = new Aiguillage(
    (new Fabrique($requete))->routeur(),
    new PresentateurCommun(),
    function (Throwable $e) {
        error_log('[' . get_class($e) . '] ' . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
);

$aiguillage->servir($requete)->envoyer();
```

