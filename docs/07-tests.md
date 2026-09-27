# 7. Le harnais de tests

`src/Test/` — **cinq fichiers** : un vérificateur et quatre doubles.

Le script `outils/verification.php` inclut aussi les vérifications du conteneur :
service partagé, service non partagé, identifiant inconnu ou dupliqué et cycle
de dépendances. Lancez-le avec `php outils/verification.php`.

---

## 7.1 Pourquoi pas PHPUnit

Question légitime, et la réponse tient en une phrase : **ce harnais doit tourner
sur la même version de PHP que la production, sur le serveur, sans
`composer install`.**

Une suite qui vérifie qu'un refactoring n'a rien cassé doit pouvoir s'exécuter
là où le code s'exécutera vraiment. Les versions récentes de PHPUnit exigent
PHP 8 ; un projet en 7.0 ne peut simplement pas les installer. Et sur un
hébergement mutualisé, Composer n'est pas toujours disponible.

**Ce n'est pas un remplaçant de PHPUnit pour un projet neuf.** C'est l'outil du
refactoring d'un code ancien. Sur un projet démarré depuis le squelette, rien
n'empêche d'ajouter PHPUnit à côté.

---

## 7.2 `Verificateur`

**Signature**

```php
final class Verificateur
{
    public function section(string $titre);
    public function egal($attendu, $obtenu, string $libelle);
    public function vrai($valeur, string $libelle);
    public function reponseEgale(int $codeAttendu, array $corpsAttendu,
                                 ReponseHttp $obtenue, string $libelle);
    public function leve(string $typeAttendu, callable $appel, string $libelle);
    public function bilan(): int;   // 0 si tout est conforme, 1 sinon
}
```

**Utilisation** — un fichier PHP ordinaire, exécuté en ligne de commande :

```php
$v = new Verificateur();
$v->section('1. Domaine');
$v->egal('1250.50', $facture->montantAffiche(), 'centimes rendus en unités');
exit($v->bilan());
```

### `egal()` — comparaison stricte, toujours

> **⚠** Avec `==`, `"0"` égale `""` et `200` égale `"200"` : exactement les
> confusions qu'un contrôle de contrat doit attraper, puisque le JSON rendu au
> consommateur, lui, distingue.

**L'écart est toujours affiché en entier**, attendu puis obtenu. Un harnais qui
se contente de « échec » oblige à rouvrir le code pour comprendre ; celui-ci doit
permettre de conclure en lisant la sortie. Les valeurs de plus de 400
caractères sont tronquées.

### `reponseEgale()` — le contrôle de contrat

Vérifie d'un coup le code HTTP, les clés rendues **et leur ordre** :

```php
$v->reponseEgale(200, [
    'statut'        => 'succes',
    'numero'        => 'F-2026-001',
    'montant'       => '1250.50',
    'etat'          => 'en_attente',
    'date_emission' => '2026-09-01',
], $presentateur->facture($facture), 'GET /facture succes');
```

Trois contrôles sont produits : le code, les clés et leur ordre, le contenu.
L'ordre compte — il fait partie de ce que lisent des consommateurs écrits à la
main, et rien d'autre dans une suite de tests ne le surveille.

### `leve()` — vérifier un refus

```php
$v->leve(
    ErreurFacturation::FACTURE_INTROUVABLE,
    function () use ($consulter) { return $consulter->executer('F-INCONNUE'); },
    'facture absente : FACTURE_INTROUVABLE'
);
```

**Elle ne guette que les `ErreurMetier`, et vérifie le type.**

> **⚠** Attraper « une exception quelconque » laisserait passer une erreur de
> frappe sur un nom de méthode : le test resterait vert en vérifiant que le code
> plante. Si l'appel lève autre chose, `leve()` le signale en nommant la classe
> et le message obtenus.

Pour une exception native (`InvalidArgumentException` sur un invariant
d'entité), attrapez-la à la main :

```php
$refuse = false;
try { new Facture('', 0, Facture::EN_ATTENTE, '2026-09-01'); }
catch (InvalidArgumentException $e) { $refuse = true; }
$v->vrai($refuse, 'un numéro vide est refusé à la construction');
```

### `bilan()`

Affiche le décompte, rappelle la liste des échecs, et **rend un code de sortie
utilisable dans un script** : `exit($v->bilan())`.

---

## 7.3 Les doubles — et l'avertissement qui vaut pour tous

> **⚠ Un double qui se comporte autrement que l'objet réel rend les tests verts
> et la production fausse.**
>
> Le cas vécu : un double de journal levait une exception en cas d'échec ; le
> cas d'usage avait donc un `catch` et un message d'erreur dédié, testé et
> conforme — alors que le dépôt réel avalait l'exception et ne levait **jamais**.
> Ce message d'erreur n'a jamais pu apparaître en production. Une branche
> entière de code, testée, morte.
>
> **Règle : avant d'écrire un double, relisez l'implémentation réelle.**

---

## 7.4 `JournalFactice`

```php
final class JournalFactice implements JournalInterface
{
    public  $traces = [];
    public   $reussit = true;

    public function enregistrer(int $acteurId, string $action, string $detail = ''): bool;
    public function compte(): int;
    public function derniere();
}
```

`enregistrer()` retourne `false` et **ne lève pas** — comme `JournalPdo`.
`$reussit = false` simule une table pleine ou verrouillée :

```php
$journal->reussit = false;
$v->egal(900, $connecter->executer('alice', 'motdepasse')['expire_dans'],
    'journal en échec : la connexion aboutit tout de même');
```

---

## 7.5 `SurveillanceFactice`

```php
final class SurveillanceFactice implements SurveillanceInterface
{
    public function __construct(int $etapes = PHP_INT_MAX, float $ecoule = 0.0);
    public static function epuiserApres(int $etapes): self;
    public  $consultations = 0;
}
```

**Le temps est la dépendance qu'on ne peut pas attendre en test.** Une boucle qui
doit s'arrêter au bout de vingt-cinq secondes ne se teste pas en patientant
vingt-cinq secondes.

```php
$s = SurveillanceFactice::epuiserApres(2);
// deux étapes passent, la troisième est refusée
```

C'est le scénario qui compte : l'arrêt **au milieu** du traitement, pas avant ni
après.

---

## 7.6 `AuthentificationFactice`

```php
final class AuthentificationFactice implements AuthentificationInterface
{
    public static function acteur(int $id, string $nom = 'test'): self;
    public static function refuse(string $type = ErreurMetier::JETON_ABSENT): self;
    public  $appels = 0;
}
```

Permet de tester un cas d'usage sans fabriquer de jeton valide.

**Les deux situations doivent être couvertes** : celle où l'appelant est connu,
et celle où l'authentification échoue. La seconde est la plus souvent oubliée,
et c'est elle qui décide si un endpoint protégé l'est vraiment :

```php
$refuse = AuthentificationFactice::refuse(ErreurMetier::JETON_EXPIRE);
$avant  = $factures->appels;
$v->leve(ErreurMetier::JETON_EXPIRE, function () use ($refuse, $factures) {
    return (new ConsulterFacture($refuse, $factures))->executer('F-2026-001');
}, 'jeton expiré : le cas d usage refuse');
$v->egal($avant, $factures->appels, 'aucune lecture du dépôt sans authentification');
```

Le second contrôle est le vrai : il prouve que l'authentification passe **avant**
la lecture.

---

## 7.7 `JetonFactice`

```php
final class JetonFactice implements JetonInterface
{
    public  $refuseAvec = null;   // un type d'ErreurMetier
}
```

Jetons sans cryptographie : la charge est encodée en JSON, telle quelle.

> **⚠ À n'utiliser que dans les tests qui ne portent pas sur la sécurité.** Il
> accepte tout ce qu'il a émis et refuse le reste, ce qui suffit à éprouver un
> cas d'usage — mais ne dit rien de la solidité de la vraie signature. Les tests
> de `JetonJwt` (signature falsifiée, `alg:none`, expiration) doivent viser
> l'implémentation réelle : c'est ce que fait `outils/verification.php`.

Il applique tout de même la règle du réel : **sans `exp`, il refuse**.

