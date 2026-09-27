# 11. Les sept pi�ges

Aucun n'est th�orique. Chacun a �t� rencontr� pendant le refactoring d'une API
de paiement en production, et c'est ce qui explique pourquoi le socle est �crit
comme il l'est.

Ils ont un point commun, et c'est ce qui les rend co�teux : **ils passent les
tests.**

---

## Pi�ge 1 � Le constructeur d'exception priv�

### Le sympt�me

```
PHP Fatal error: Access level to App\Paiement\Domain\ErreurMetier::__construct()
must be public (as in class Exception)
```

Sept endpoints, tous hors service. Pas un seul appel servi.

### La cause

```php
final class ErreurMetier extends Exception
{
    private function __construct($type, $detail)   // ? fatal sous PHP 7.0
    {
        parent::__construct($detail);
        $this->type = $type;
    }
}
```

L'intention �tait bonne : forcer le passage par les fabriques nomm�es. PHP
l'interdit � une m�thode qui en red�finit une autre ne peut pas r�duire sa
visibilit�, et `Exception::__construct()` est publique.

**Le refus est � la d�claration, pas � l'usage.** La classe ne se charge pas ;
tout ce qui l'importe tombe avec elle. Et comme `ErreurMetier` est import�e par
les sept endpoints, l'API enti�re �tait morte.

### Pourquoi rien ne l'avait vu

| Outil | Pourquoi il ne voit rien |
|---|---|
| `php -l` | ne v�rifie que la **syntaxe** ; ne d�clare pas les classes |
| 251 contr�les verts | tournaient sous **PHP 8.4**, qui a rel�ch� la r�gle pour les constructeurs |
| revue de code | la construction est parfaitement l�gale sous PHP 8 |

C'est l'asym�trie qui tue : poste de d�veloppement en PHP 8, production en
PHP 7.0.

### Ce que le socle en fait

**1. Le constructeur est public, avec le commentaire qui explique pourquoi** �
sinon quelqu'un le � corrigera � dans six mois.

**2. `outils/compat.php`** d�clare toutes les classes et laisse PHP se plaindre.
C'est ce que `php -l` ne peut pas faire.

**3. `outils/verification.php`** applique la r�gle stricte **par r�flexion**,
quelle que soit la version qui l'ex�cute :

```php
$constructeur = (new ReflectionClass(ErreurMetier::class))->getConstructor();
$v->vrai($constructeur !== null && $constructeur->isPublic(), '�');
```

C'est le seul contr�le qui prot�ge depuis un poste plus r�cent que la
production.

### La le�on g�n�rale

> **Une suite de tests verte sous une version de PHP ne prouve rien pour une
> autre.** Lancez `lint` avec le binaire de production.

---

## Pi�ge 2 � La connexion ouverte trop t�t

### Le sympt�me

Base indisponible. Une requ�te **sans en-t�te `Authorization`** re�oit :

```json
{"statut":"erreur","message":"erreur interne"}
```

au lieu de :

```json
{"statut":"erreur","message":"token introuvable"}
```

Le client cherche une panne chez lui ; il lui manquait juste un jeton.

### La cause

La racine de composition c�blait l'authentificateur dans tous les cas d'usage.
Le construire exigeait un d�p�t, donc la connexion :

```php
public function authentificateur()
{
    return new Authentificateur(
        $this->requete(),
        $this->jetons(),
        new DepotUtilisateurPdo($this->connexion()->pdo())   // ? ouvre la socket
    );
}
```

L'endpoint �chouait **avant** d'avoir regard� la requ�te.

### Pourquoi rien ne l'avait vu

Les tests travaillaient avec des doubles : aucune connexion, donc aucune
ouverture pr�matur�e. Le d�faut n'existe que lorsque la base est injoignable �
c'est-�-dire jamais, en test.

Il a �t� trouv� par une fumigation : lancer l'API avec un DSN volontairement
faux, et regarder ce qu'elle r�pond.

### Ce que le socle en fait

**Deux niveaux de paresse superpos�s** :

| Classe | Ce qu'elle retarde |
|---|---|
| `FabriqueConnexion` | la socket, jusqu'au premier `pdo()` |
| `AuthentificationDifferee` | la construction de l'authentificateur, jusqu'au premier `identifier()` |

Et la v�rification, de bout en bout :

```
JWT_SECRET=x DB_HOTE=255.255.255.255 php -S 127.0.0.1:8000 -t public public/index.php
curl "http://127.0.0.1:8000/facture?numero=F-1"
# 400 {"statut":"erreur","message":"token introuvable"}
```

### La le�on g�n�rale

> **C�bler n'est pas travailler.** La racine de composition assemble des objets ;
> elle n'ouvre rien, n'appelle rien, ne lit rien.

---

## Pi�ge 3 � Le pr�sentateur unique

### Le sympt�me

Base indisponible. **Tous** les endpoints r�pondent :

```json
{"statut":"erreur","message":"identifiants invalides"}
```

Les consommateurs ont v�rifi� leurs identifiants, les ont r�g�n�r�s, ont ouvert
un ticket. Le probl�me �tait ailleurs.

### La cause

Le point d'entr�e attrapait tout et rendait l'erreur avec **un pr�sentateur de
secours unique** � celui de la connexion, qui traduit `IDENTIFIANTS_INVALIDES`
en 403.

```php
try {
    // c�blage + ex�cution
} catch (Throwable $e) {
    return $this->presentateurConnexion->traduire($e);   // ? toujours le m�me
}
```

### Ce que le socle en fait

**Chaque route porte son propre pr�sentateur** :

```php
$routeur->ajouter(
    'facture',
    function () { /* le contr�leur */ },
    function () { return new PresentateurFacture(); },   // ? celui de CETTE route
    ['GET']
);
```

`Aiguillage` construit le pr�sentateur **avant** le `try`, puis ex�cute dedans :
une panne de c�blage est rendue au format de l'endpoint appel�.

Le pr�sentateur de secours, lui, ne sert qu'aux erreurs qui ne concernent aucune
route � chemin inconnu, m�thode refus�e � et n'a **aucune d�pendance**, donc rien
qui puisse �chouer � son tour.

Un contr�le d'�quivalence le v�rifie : une route dont le c�blage l�ve est rendue
en 500 par son propre pr�sentateur, et le message technique ne fuit pas.

### La le�on g�n�rale

> **Un message d'erreur est une information de diagnostic.** S'il est faux, il
> envoie tout le monde chercher au mauvais endroit.

---

## Pi�ge 4 � Le double qui ment

### Le sympt�me

Aucun. C'est le pire des quatre premiers.

Un endpoint avait un chemin d'erreur `403 {"message": "erreur sauvegarde action"}`,
test�, vert, document�. **Il n'avait jamais pu se produire en production.**

### La cause

Le double de journal levait une exception en cas d'�chec :

```php
class JournalFactice
{
    public function enregistrer($acteur, $action)
    {
        if (!$this->reussit) {
            throw new RuntimeException('echec');   // ? le vrai d�p�t ne l�ve JAMAIS
        }
    }
}
```

Le d�p�t r�el, lui, attrapait tout et retournait sans rien dire. Le cas d'usage
avait donc un `catch` qui ne pouvait jamais se d�clencher, et un message d'erreur
qui ne pouvait jamais sortir.

Une branche enti�re de code : test�e, verte, morte.

### Ce que le socle en fait

**Le contrat tranche, pas l'impl�mentation.** `JournalInterface::enregistrer()`
rend un `bool` et **ne l�ve jamais** � c'est �crit dans l'interface, appliqu� par
`JournalPdo`, et respect� par `JournalFactice`.

L'avertissement est en t�te du double, l� o� quelqu'un le lira :

> *Un double qui se comporte autrement que l'objet r�el rend les tests verts et
> la production fausse. Avant d'�crire un double, relisez l'impl�mentation
> r�elle.*

Le contr�le correspondant v�rifie le comportement du double lui-m�me :

```php
$journal->reussit = false;
$v->egal(false, $journal->enregistrer(1, 'x'), 'le journal rend false et NE L�VE PAS');
```

### La le�on g�n�rale

> **Un double est une hypoth�se sur le r�el.** Une hypoth�se fausse produit des
> tests verts sur du code mort.

---

## Pi�ge 5 � Le message technique qui fuit

### Le sympt�me

```json
{"statut":"erreur","message":"SQLSTATE[HY000] [2002] Connection refused"}
```

Le consommateur apprend le moteur de base, l'h�te, le port � et, selon
l'erreur, l'utilisateur SQL.

Variante plus discr�te, rencontr�e aussi :

```json
{"statut":"erreur","statut":"Impossible d'obtenir le verrou external_api_token_lock"}
```

Aucune donn�e sensible, mais le nom interne d'un verrou : de quoi cartographier
l'impl�mentation, et de quoi laisser croire � une erreur de la part de l'appelant.

### La cause

Un `catch` qui relaie `$e->getMessage()` au lieu de traduire.

### Ce que le socle en fait

**La r�gle est inscrite dans `PresentateurAbstrait`** :

> *Un pr�sentateur ne laisse jamais passer le message d'une exception technique.
> Traduisez, ne relayez pas.*

`PresentateurCommun` traduit `ECHEC_ECRITURE` et `ECHEC_JOURNALISATION` en
`panne()` � le d�tail reste dans `getMessage()`, pour le journal.

**Une seule exception, assum�e** : `PARAMETRE_MANQUANT` laisse passer son d�tail,
parce qu'il nomme les param�tres absents. C'est une information utile �
l'appelant, qui ne r�v�le rien du syst�me � et elle est compos�e par une fabrique
nomm�e, pas par une couche technique.

Deux contr�les d'�quivalence v�rifient qu'aucun `SQLSTATE` n'appara�t dans un
corps de r�ponse.

### La le�on g�n�rale

> **Le consommateur ne doit pas conna�tre les d�tails d'impl�mentation.** Ni le
> moteur, ni l'h�te, ni le nom d'un verrou.

---

## Pi�ge 6 � Les tables non pr�fix�es

### Le sympt�me

Une copie de d�veloppement qui lit � et �crit � en production. Sans erreur, sans
avertissement.

### La cause

Sur 61 requ�tes, 42 nommaient leur base (`flexeau_db.factures`) et **19 ne la
nommaient pas** (`factures`). Ces 19 s'adressaient � la base par d�faut du compte
MySQL, qui n'a rien � voir avec ce que le code croit ouvrir.

Dupliquer le projet et changer le DSN ne suffit donc pas : les 42 requ�tes
qualifi�es partent vers la base de d�veloppement, les 19 autres vers la
production. Le pire des deux mondes.

### Ce que le socle en fait

**Un `USE` explicite, juste apr�s la connexion** :

```php
$pdo = new PDO($dsn, $utilisateur, $motDePasse, $options);
if ($this->base !== null) {
    $pdo->exec('USE `' . $this->base . '`');
}
```

Les requ�tes non qualifi�es suivent alors la m�me base que les autres. Ce n'est
pas une excuse pour ne pas qualifier � c'est le filet pour le jour o� l'on
oublie.

> **?** Le filet ne couvre que les connexions ouvertes par `FabriqueConnexion`.
> Un script d'analyse qui ouvre la sienne doit �mettre le m�me `USE`.

### La le�on g�n�rale

> **Ce qui n'est pas explicite est d�cid� par la configuration du serveur.** Et
> la configuration du serveur n'est pas dans votre d�p�t.

---

## Pi�ge 7 � L'ordre des cl�s JSON

### Le sympt�me

Aucun de votre c�t�. Un consommateur se casse, et vous n'avez � rien chang� �.

### La cause

```php
// avant
return ['statut' => 'succes', 'token' => $jeton, 'expire_dans' => 3600];
// apr�s un � rangement �
return ['token' => $jeton, 'statut' => 'succes', 'expire_dans' => 3600];
```

`json_encode` respecte l'ordre du tableau. Un client �crit � la main � parseur
maison, expression r�guli�re, comparaison de cha�ne compl�te � s'en aper�oit.

**On ne peut pas savoir qui fait �a.** Sur une API consomm�e par plusieurs
services tiers dont certains ont dix ans, l'hypoth�se prudente est : quelqu'un le
fait.

### Ce que le socle en fait

**`ReponseHttp` documente la r�gle**, et `Verificateur::reponseEgale()` la
surveille :

```php
$v->egal(array_keys($corpsAttendu), array_keys($obtenue->corps()),
    $libelle . ' -- cles et ordre');
```

Trois contr�les sont produits par appel : le code, les cl�s **et leur ordre**, le
contenu. Une r�organisation de code qui change l'ordre fait rougir la suite.

### La le�on g�n�rale

> **Le contrat d'une API, ce n'est pas seulement ce qu'elle rend : c'est aussi
> comment.** Codes HTTP, noms de cl�s, ordre des cl�s, et jusqu'aux fautes de
> frappe des messages.

---

## Ce qu'ils ont en commun

| Pi�ge | Passe les tests parce que� |
|---|---|
| 1. Constructeur priv� | les tests tournent sous une autre version de PHP |
| 2. Connexion trop t�t | les tests n'ont pas de base � faire tomber |
| 3. Pr�sentateur unique | les tests ne font pas �chouer le c�blage |
| 4. Double qui ment | le double est l'hypoth�se, et elle est fausse |
| 5. Message qui fuit | personne ne v�rifie ce qui **ne doit pas** appara�tre |
| 6. Tables non pr�fix�es | les tests tournent sur une seule base |
| 7. Ordre des cl�s | personne ne compare l'ordre |

Aucun ne se voit en lisant le code, et aucun ne se voit en lan�ant une suite
ordinaire. Ils se voient quand on **v�rifie l'absence** � pas d'exception qui
fuit, pas de connexion ouverte, pas de `SQLSTATE` dans la r�ponse, pas de cl�
d�plac�e.

C'est ce que font les 55 contrôles de `verification.php` (57 avec PDO SQLite) et les 53 du squelette.

