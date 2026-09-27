# 9. Le squelette

`squelette/` � un projet complet et fonctionnel, � copier pour d�marrer. Deux
endpoints : `POST /login` et `GET /facture`.

**Il n'est pas l� pour �tre gard� tel quel : il est l� pour montrer o� va quoi.**
Son domaine (facturation) est destin� � �tre remplac�, pas �tendu.

```
squelette/
+-- README.md, composer.json, autoload.php
+-- public/
�   +-- index.php
�   +-- .htaccess
+-- src/
�   +-- Fabrique.php
�   +-- Domain/
�   �   +-- ErreurFacturation.php
�   �   +-- Entite/Facture.php
�   �   +-- Contrat/
�   �       +-- DepotFactureInterface.php
�   �       +-- DepotUtilisateurInterface.php
�   +-- Application/
�   �   +-- Connecter.php
�   �   +-- ConsulterFacture.php
�   +-- Infrastructure/
�   �   +-- DepotFacturePdo.php
�   �   +-- DepotUtilisateurPdo.php
�   �   +-- ResolveurDepot.php
�   +-- Presentation/
�       +-- Controleur/
�       �   +-- ControleurConnexion.php
�       �   +-- ControleurFacture.php
�       +-- Presentateur/
�           +-- PresentateurConnexion.php
�           +-- PresentateurFacture.php
+-- tests/architecture/
    +-- doubles.php
    +-- equivalence.php
```

---

## 9.1 `public/index.php`

**Tout passe par ici, et c'est le seul fichier expos� au web.** Les sources sont
hors de `public/` : un fichier qui n'est pas servi ne peut pas �tre lu par
erreur, quelle que soit la configuration du serveur.

```php
require __DIR__ . '/../autoload.php';

ini_set('display_errors', '0');   // expose les chemins du serveur
ini_set('log_errors', '1');

$requete    = Requete::depuisGlobales();
$aiguillage = new Aiguillage(
    (new Fabrique($requete))->routeur(),
    new PresentateurCommun(),
    function (Throwable $e) { error_log(/* � */); }
);
$aiguillage->servir($requete)->envoyer();
```

> **? Ce fichier ne doit jamais grossir.** S'il commence � contenir des `if` sur
> le chemin demand�, la logique est en train de remonter du routeur vers lui.

`display_errors` � `0` n'est pas une coquetterie : le message d'une exception PDO
contient l'h�te et parfois les identifiants de connexion.

---

## 9.2 `public/.htaccess`

Trois blocs :

**1. La r��criture** � tout vers `index.php`, sauf les fichiers r�ellement
pr�sents.

**2. La r�cup�ration d'`Authorization`** :

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

> **?** Apache retire cet en-t�te avant PHP quand `CGIPassAuth` est d�sactiv� �
> configuration fr�quente en mutualis�. Sans ces deux lignes, **tous** les
> endpoints authentifi�s r�pondent � token introuvable � alors que le client
> l'envoie bien.

**3. La configuration.** Composer copie .env.example vers .env a la creation du projet. Renseignez les variables locales dans .env ; ce fichier est ignore par Git. En production, les variables du serveur ou du vhost sont prioritaires.

---

## 9.3 `src/Domain/Entite/Facture.php`

```php
final class Facture
{
    const EN_ATTENTE = 'en_attente';
    const PAYEE      = 'payee';
    const ANNULEE    = 'annulee';

    public function __construct(string $numero, int $montantCentimes,
                                string $statut, string $dateEmission);
    public function numero(): string;
    public function montantCentimes(): int;
    public function statut(): string;
    public function dateEmission(): string;
    public function estPayable(): bool;
    public function montantAffiche(): string;
}
```

**Deux choses � remarquer, et elles valent pour toutes vos entit�s.**

**1. Le montant est un entier, en centimes.**

> **?** Un `float` ne repr�sente pas exactement 0,10 : additionnez-en assez et
> le total se met � finir par des chiffres impossibles. En comptabilit�, cet
> �cart est un incident.

`montantAffiche()` fait la conversion pour l'API � le domaine compte en
centimes, pas l'API.

**2. Aucune annotation, aucun lien vers la base.** L'entit� ignore qu'elle est
stock�e, et donc comment. C'est ce qui permet de changer de sch�ma, ou de r�unir
deux tables en une entit�, sans toucher aux cas d'usage.

**`estPayable()` est une r�gle m�tier, plac�e dans l'entit�.** La m�me question
sera pos�e par le paiement, par la relance et par l'export ; une seule r�ponse,
ici, au lieu de trois `if` qui finiront par diverger.

**Les invariants se d�fendent dans le constructeur** : une facture ne peut pas
exister sans num�ro, avec un montant n�gatif, un statut inconnu ou une date
invalide au format `AAAA-MM-JJ`. Mieux vaut �chouer � la construire que la
promener � moiti� remplie dans toute l'application.

---

## 9.4 `src/Domain/ErreurFacturation.php`

```php
final class ErreurFacturation extends ErreurMetier
{
    const FACTURE_INTROUVABLE = 'facture_introuvable';
    const FACTURE_DEJA_PAYEE  = 'facture_deja_payee';

    public static function factureIntrouvable(string $numero): self;
    public static function factureDejaPayee(string $numero): self;
}
```

Elle h�rite des dix types communs et n'ajoute que ce que la facturation sait dire
de plus.

**Remarquez qu'il n'y a pas de code HTTP ici.** Le domaine ignore HTTP ; c'est le
pr�sentateur qui d�cide qu'une facture introuvable vaut 404.

**Le num�ro va dans le `detail`**, donc au journal � pas forc�ment dans la
r�ponse. Le pr�sentateur tranche.

---

## 9.5 `src/Domain/Contrat/` � les deux d�p�ts

```php
interface DepotFactureInterface extends DepotInterface
{
    public function trouverParNumero(string $numero);
    public function impayeesDe(int $abonneId): array;
}

interface DepotUtilisateurInterface extends DepotInterface
{
    public function parIdentifiants(string $identifiant, string $motDePasse);
    public function parId(int $id);
}
```

**Le contrat est �crit par celui qui consomme, pas par celui qui impl�mente.**
C'est l'inversion de d�pendance : ces interfaces vivent dans le domaine,
l'impl�mentation SQL vit dans l'infrastructure.

**La v�rification du mot de passe est dans l'impl�mentation, pas dans le
contrat** : le domaine demande � qui est-ce, avec ces identifiants ? � et ne veut
conna�tre ni l'algorithme de hachage, ni la forme de la table.

**Aucune m�thode ne rend le mot de passe, m�me hach�.** Ce qui ne sort pas du
d�p�t ne peut pas se retrouver dans un journal ou une r�ponse.

---

## 9.6 `src/Application/Connecter.php`

```php
final class Connecter
{
    public function __construct(DepotUtilisateurInterface $utilisateurs,
                                JetonInterface $jetons,
                                JournalInterface $journal,
                                int $dureeJeton = 3600);
    public function executer(string $identifiant, string $motDePasse): array;
}
```

**Anatomie d'un cas d'usage** � le mod�le vaut pour tous les autres :

- il re�oit des **interfaces**, jamais des objets concrets ;
- il ne conna�t ni HTTP, ni SQL, ni JSON ;
- il rend une donn�e ou l�ve une `ErreurMetier`, et rien d'autre ;
- il tient en un �cran. Au-del�, il fait le travail de deux.

Aucun `echo`, aucun `header`, aucun code HTTP : c'est ce qui permet de l'�prouver
enti�rement en m�moire, avec des doubles.

**Trois d�cisions y sont �crites explicitement** :

**1. Une seule erreur pour deux cas.**

```php
if ($identite === null) {
    throw ErreurMetier::identifiantsInvalides();
}
```

> **?** Distinguer � compte inconnu � de � mot de passe faux � permet d'�num�rer
> les comptes existants.

**2. Le jeton ne transporte qu'un identifiant.**

```php
$jeton = $this->jetons->emettre(['sub' => $identite->id()], $this->dureeJeton);
```

La charge est lisible par tous ; le nom, le r�le ou l'adresse n'ont rien � y
faire, et seront de toute fa�on relus en base � chaque requ�te.

**3. Le journal ne bloque pas la connexion.**

```php
// Le journal rend false plut�t que de lever.
// ICI, ON CHOISIT DE POURSUIVRE : refuser une connexion valide parce que
// sa trace n'a pas pu s'�crire serait pire que la trace perdue.
$this->journal->enregistrer($identite->id(), 'connexion');
```

**Ce choix doit �tre �crit, sinon personne ne saura qu'il a �t� fait.**

---

## 9.7 `src/Application/ConsulterFacture.php`

```php
final class ConsulterFacture
{
    public function __construct(AuthentificationInterface $authentification,
                                DepotFactureInterface $factures);
    public function executer(string $numero): Facture;
}
```

**L'ordre des trois �tapes n'est pas arbitraire** :

1. valider l'entr�e � inutile d'authentifier pour une requ�te vide ;
2. identifier l'appelant � **avant tout acc�s aux donn�es** ;
3. lire.

> **?** Inverser 2 et 3, c'est permettre � un appelant anonyme d'apprendre, au
> choix des r�ponses, quelles factures existent.

Le r�sultat de `identifier()` n'est pas utilis� ici, mais l'appel, lui, est
indispensable. La suite d'�quivalence le v�rifie en comptant les acc�s au d�p�t.

---

## 9.8 `src/Infrastructure/` � les trois impl�mentations

### `DepotFacturePdo`

**Tout le SQL du projet vit dans cette couche.** Un cas d'usage qui contient un
`SELECT` ne peut plus �tre test� sans base � et c'est toujours par l� qu'une
architecture propre commence � se d�faire.

`hydrater()` est la fronti�re : au-dessus on parle en `Facture`, en dessous en
lignes de table. Elle convertit aussi les types, puisque PDO rend des cha�nes
m�me pour les colonnes num�riques.

### `DepotUtilisateurPdo`

**Deux points de s�curit�, et aucun n'est n�gociable.**

**1. Le mot de passe n'est jamais compar� en SQL.**

> **?** Un `WHERE mot_de_passe = :mdp` suppose un stockage en clair ou un
> hachage r�versible, et confie la comparaison au moteur. On charge le hachage,
> et `password_verify` � qui compare en temps constant � tranche.

Un hachage factice est tout de m�me v�rifi� sur compte inconnu : sans cela, un
compte inexistant r�pond nettement plus vite qu'un mot de passe faux, ce qui
suffit � �num�rer les comptes.

**2. Le compte d�sactiv� est filtr� dans `parId()`.** C'est ce qui rend un jeton
encore valide inop�rant d�s la d�sactivation, au lieu d'attendre son expiration.

### `ResolveurDepot`

Cinq lignes, et pourtant c'est elle qui fait la diff�rence entre � le jeton dit
que c'est l'utilisateur 42 � et � l'utilisateur 42 existe toujours et est actif �.

> **?** La tentation permanente est de la court-circuiter � l'identifiant est
> dans le jeton, pourquoi relire la base ? **Parce qu'un jeton �mis ce matin
> parle d'un compte tel qu'il �tait ce matin.**

---

## 9.9 `src/Presentation/Controleur/`

```php
final class ControleurFacture
{
    public function __construct(ConsulterFacture $consulter, PresentateurFacture $presentateur);
    public function __invoke(Requete $requete): ReponseHttp;
}
```

**Un contr�leur ne d�cide rien.** Il extrait, il appelle, il pr�sente. Trois
lignes utiles, et c'est normal : la r�gle m�tier est dans le cas d'usage, le
format de r�ponse dans le pr�sentateur.

**Il ne contient aucun `try`.** Les `ErreurMetier` remontent jusqu'�
`Aiguillage`, qui les confie au pr�sentateur de la route. Attraper ici
dupliquerait ce m�canisme, avec le risque de le faire diff�remment d'un endpoint
� l'autre.

**`__invoke()`** permet de passer le contr�leur directement comme action de
route : `$action($requete)`.

---

## 9.10 `src/Presentation/Presentateur/`

Chacun r�pond � lui seul � la question **� que rend cet endpoint, dans tous les
cas ? �**. C'est le but : le contrat tient sur un �cran, se relit avant une mise
en production, et se teste sans base ni serveur.

`PresentateurFacture` illustre un d�tail qui compte :

```php
case ErreurFacturation::FACTURE_INTROUVABLE:
    // Le num�ro demand� n'est PAS r�p�t� dans le message : il vient de
    // l'appelant, et le renvoyer tel quel expose au moindre client qui
    // l'afficherait sans �chapper.
    return $this->echec(404, 'erreur', 'facture introuvable');
```

Un contr�le d'�quivalence envoie `<script>` comme num�ro et v�rifie qu'il ne
ressort pas.

---

## 9.11 `src/Fabrique.php`

Voir [`06-composition.md`](06-composition.md) � 6.7 pour le d�tail. Trois points
propres au squelette :

- chaque route d�clare **son** pr�sentateur ;
- l'authentification est **diff�r�e**, parce que la construire touche la base ;
- JWT_SECRET n'a pas de valeur de repli : renseignez-le dans .env ou dans l'environnement du serveur, sinon le service de jetons refuse de demarrer.

---

## 9.12 `tests/architecture/`

### `doubles.php`

Les doubles **propres au projet** : `DepotFactureFactice`,
`DepotUtilisateurFactice`. Les doubles g�n�riques � journal, surveillance, jeton,
authentification � viennent de la biblioth�que (`PhpCleanCode\Test`).

`DepotUtilisateurFactice::desactiver()` simule le cas qui compte : le jeton reste
valide, le compte non.

### `equivalence.php`

**43 contr�les**, sans base, sans serveur, sans r�seau. Tout tourne en m�moire,
en une fraction de seconde � c'est ce qui permet de la relancer apr�s chaque
modification. Une suite qu'on ne relance pas ne prot�ge de rien.

| Section | Contr�les | Ce qui est v�rifi� |
|---|---|---|
| 1. Domaine | 4 | conversion des centimes, r�gle `estPayable`, invariant du constructeur |
| 2. Cas d'usage | 10 | cas nominal, refus, **ordre authentification/lecture**, non-�num�ration des comptes, journal en �chec |
| 3. Contrat HTTP | 21 | codes, cl�s, **ordre des cl�s**, non-fuite du d�tail technique, indiscernabilit� des jetons invalides |
| 4. Routage | 8 | pr�fixe retir�, casse ignor�e, 404, 405, panne de c�blage rendue par le bon pr�sentateur |

**La section 3 est celle qui prot�ge les consommateurs tiers.** Un refactoring
peut tout r�organiser en dessous : tant que ces contr�les passent, aucun client
n'a besoin d'�tre pr�venu.

---

## 9.13 Essayer sans Apache

```bash
cd squelette
JWT_SECRET=test BASE_URI= php -S 127.0.0.1:8000 -t public public/index.php

curl "http://127.0.0.1:8000/facture?numero=F-1"
# {"statut":"erreur","message":"token introuvable"}
```

Avec une base volontairement injoignable, la fumigation compl�te donne :

| Requ�te | R�ponse |
|---|---|
| `GET /facture?numero=F-1` | `400 {"statut":"erreur","message":"token introuvable"}` |
| `GET /facture` + jeton bidon | `401 {"statut":"erreur","message":"token invalide"}` |
| `GET /inconnu` | `404 {"statut":"erreur","message":"ressource introuvable"}` |
| `GET /login` | `405 {"statut":"erreur","message":"methode non autorisee"}` |
| `POST /login` corps vide | `400 {"statut":"erreur","message":"parametre manquant : �"}` |
| `POST /login` identifiants | `500 {"statut":"erreur","message":"erreur interne"}` |

**La premi�re ligne est celle qui valide tout le reste** : base morte, et
pourtant le diagnostic juste. C'est la paresse de `FabriqueConnexion` et
d'`AuthentificationDifferee`, v�rifi�e de bout en bout.

