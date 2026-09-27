# 3. La couche Infrastructure

`src/Infrastructure/` � **six fichiers**. Tout ce qui parle au monde ext�rieur :
base de donn�es, cryptographie, horloge.

C'est la seule couche qui a le droit de conna�tre PDO. Elle d�pend du domaine
(elle impl�mente ses contrats) ; le domaine ne la conna�t pas.

---

## 3.1 `FabriqueConnexion`

**Signature**

```php
final class FabriqueConnexion
{
    public function __construct(
        string $dsn,
        string $utilisateur,
        string $motDePasse,
        $base = null,
        array $options = []
    );
    public function pdo(): PDO;          // @throws RuntimeException
    public function estOuverte(): bool;
}
```

**R�le** � Une connexion PDO unique, ouverte au dernier moment.

**Pourquoi paresseuse** � Tant que personne n'appelle `pdo()`, aucune socket
n'est ouverte. La racine de composition peut donc c�bler toute l'application �
d�p�ts, journal, authentification � **sans toucher la base**. Un endpoint qui
refuse une requ�te malform�e r�pond sans avoir jamais consult� le serveur.

C'est ce qui permet � une requ�te sans jeton de recevoir � token introuvable �
m�me quand la base est morte, au lieu d'une panne g�n�rique.

**Le param�tre `$base` est un filet de s�curit�.** S'il est fourni, un
`USE \`base\`` explicite est �mis juste apr�s la connexion. La connexion n'est
conserv�e qu'apr�s son succ�s : un �chec ne peut pas laisser r�utiliser la base
par d�faut du compte.

> **?** D�s qu'une seule requ�te du projet nomme une table sans pr�fixer sa
> base, c'est la base par d�faut du compte MySQL qui d�cide � et une copie de
> d�veloppement se met � lire **et �crire** en production sans qu'aucun test ne
> le signale. Voir [`11-pieges.md`](11-pieges.md), pi�ge n� 6.

**Les options par d�faut**, �crasables :

```php
PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION   // �chouer bruyamment
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC         // pas de doublons num�riques
PDO::ATTR_EMULATE_PREPARES   => false                    // vraies requ�tes pr�par�es
```

`EMULATE_PREPARES => false` compte : avec l'�mulation, c'est PDO qui interpole
les valeurs dans la cha�ne SQL, et la protection contre l'injection d�pend alors
du bon r�glage de l'encodage.

**L'exception est retraduite** :

```php
catch (PDOException $e) {
    throw new RuntimeException('connexion a la base impossible', 0, $e);
}
```

Le message de PDO contient l'h�te, le port et parfois l'utilisateur. Il ne doit
jamais remonter au consommateur. L'original reste accessible par
`getPrevious()`, pour le journal.

**Utilisation**

```php
$connexion = new FabriqueConnexion(
    'mysql:host=127.0.0.1;dbname=exemple;charset=utf8mb4',
    'exemple', $motDePasse,
    'exemple'          // ? le USE explicite
);
// rien n'est ouvert � ce stade
$connexion->estOuverte();   // false
```

---

## 3.2 `DepotPdo`

**Signature**

```php
abstract class DepotPdo
{
    public function __construct(FabriqueConnexion $connexion);

    final protected function pdo(): PDO;
    final protected function executer(string $sql, array $parametres = []): PDOStatement;
    final protected function uneLigne(string $sql, array $parametres = []);
    final protected function lignes(string $sql, array $parametres = []): array;
    final protected function scalaire(string $sql, array $parametres = []);   // mixed|null
}
```

**R�le** � Socle commun des d�p�ts SQL.

**Pourquoi pas de CRUD** � Une classe m�re qui offrirait `find()`, `save()` et
`delete()` imposerait une table par entit� et une cl� primaire nomm�e `id`, ce
qui est faux d�s la premi�re base h�rit�e. Elle fournit ce qui se r�p�te
vraiment � pr�parer, ex�cuter, ramener une ligne ou un scalaire � et rien de
plus.

**La connexion arrive par la fabrique, jamais par un PDO d�j� ouvert.** C'est ce
qui garde la paresse jusqu'� la premi�re requ�te r�ellement ex�cut�e.

**Tous les helpers sont `final protected`** : un d�p�t les utilise, personne ne
les red�finit, et aucun code ext�rieur n'acc�de au PDO.

> **? Toujours passer par `$parametres`.** Concat�ner une valeur dans `$sql`,
> m�me � s�re � parce qu'elle vient d'un entier, est la porte d'entr�e des
> injections SQL. Les noms de tables et de colonnes, eux, ne peuvent pas �tre
> li�s : s'ils sont dynamiques, validez-les contre une liste blanche.

**`uneLigne()` et `scalaire()` rendent `null`, pas `false`.** PDO renvoie
`false` quand il n'y a pas de r�sultat, ce qui se confond avec une valeur `0` ou
une cha�ne vide l�gitime. La normalisation en `null` supprime cette ambigu�t�.

**Utilisation**

```php
final class DepotFacturePdo extends DepotPdo implements DepotFactureInterface
{
    public function trouverParNumero(string $numero)
    {
        $ligne = $this->uneLigne(
            'SELECT numero, montant_centimes, statut, date_emission'
            . ' FROM factures WHERE numero = :numero LIMIT 1',
            [':numero' => $numero]
        );
        return $ligne === null ? null : $this->hydrater($ligne);
    }
}
```

**La m�thode `hydrater()` est la fronti�re.** Au-dessus d'elle on parle en
`Facture` ; en dessous, en lignes de table. Elle convertit aussi les types,
puisque PDO rend des cha�nes m�me pour les colonnes num�riques.

---

## 3.3 `JetonJwt`

**Signature**

```php
final class JetonJwt implements JetonInterface
{
    public function __construct(string $secret);   // @throws InvalidArgumentException
    public function emettre(array $charge, int $dureeSecondes): string;
    public function verifier(string $jeton): array;
}
```

**R�le** � JWT sign� en HS256, sans d�pendance externe.

**Format** � Trois segments en base64url s�par�s par des points : ent�te, charge,
signature. La signature couvre `entete.charge` ; changer un octet de l'un ou de
l'autre l'invalide.

**Trois pi�ges, tous �vit�s ici** :

**1. L'algorithme annonc� par le jeton n'est jamais consult�.** Une biblioth�que
qui lit `alg` dans l'ent�te accepte un jeton disant `"alg":"none"` �
c'est-�-dire non sign�. HS256 est impos�, en dur.

**2. La comparaison de signature passe par `hash_equals()`.** Un `===` rend son
verdict d'autant plus vite que les cha�nes diff�rent t�t, ce qui suffit �
reconstituer une signature valide octet par octet.

**3. `exp` est obligatoire.** Un jeton sans date d'expiration ne p�rime jamais ;
il est refus� (`JETON_MALFORME`) plut�t que de se voir accorder une validit�
perp�tuelle.

> **? La charge n'est pas un secret.** Elle est *encod�e*, pas chiffr�e :
> n'importe qui la lit avec un d�codeur base64. N'y mettez ni mot de passe, ni
> donn�e personnelle. Mettez-y le strict minimum pour retrouver l'acteur �
> typiquement `{"sub": 42}`.

**Un secret vide fait �chouer la construction.** Un secret vide signe tout de
m�me, silencieusement, et tous les jetons deviennent forgeables. Mieux vaut
�chouer au d�marrage.

**Utilisation**

```php
$jwt   = new JetonJwt(getenv('JWT_SECRET'));
$jeton = $jwt->emettre(['sub' => $identite->id()], 3600);
// iat et exp sont ajout�s automatiquement

$charge = $jwt->verifier($jeton);   // ['sub' => 42, 'iat' => �, 'exp' => �]
```

Sept contr�les de `outils/verification.php` portent sur cette classe, dont
quatre sur ce qu'elle doit **refuser**.

---

## 3.4 `JournalPdo`

**Signature**

```php
final class JournalPdo extends DepotPdo implements JournalInterface
{
    public function __construct(FabriqueConnexion $connexion, string $table = 'journal');
    public function enregistrer(int $acteurId, string $action, string $detail = ''): bool;
}
```

**R�le** � Journal en base, qui ne fait jamais tomber l'appelant.

**Le `catch (Throwable)` est une promesse, pas une n�gligence.** Le contrat
(`JournalInterface`) dit que cette m�thode ne l�ve pas ; le `catch` est la
traduction d'un �chec d'�criture en `false`, pour que l'op�ration m�tier d�j�
accomplie ne soit pas annul�e par sa propre trace.

**Sch�ma attendu** � La table doit avoir `acteur_id`, `action`, `detail`,
`horodatage` :

Le nom de table est un identifiant SQL, pas une valeur paramétrable : le
constructeur n'accepte que les lettres ASCII, les chiffres et `_`, avec une
lettre ou `_` en premier caractère, et une longueur maximale de 64 caractères.
Les valeurs d'action et de détail restent, elles, liées par paramètres PDO.

```sql
CREATE TABLE journal (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    acteur_id  INT UNSIGNED NOT NULL,
    action     VARCHAR(64)  NOT NULL,
    detail     TEXT         NULL,
    horodatage DATETIME     NOT NULL,
    INDEX (acteur_id, horodatage)
) ENGINE=InnoDB;
```

Si votre sch�ma diff�re, n'adaptez pas la table : �crivez votre propre
impl�mentation de `JournalInterface`. C'est � cela que sert le contrat.

---

## 3.5 `SurveillanceTimeout`

**Signature**

```php
final class SurveillanceTimeout implements SurveillanceInterface
{
    public function __construct(float $limiteSecondes = 25.0, float $margeSecondes = 5.0);
    public function tempsRestant(): bool;
    public function ecoule(): float;
}
```

**R�le** � Garde-temps d�marr� � la construction. PHP 7.3 et les versions
suivantes utilisent l'horloge monotone `hrtime()`. Sous PHP 7.0 � 7.2, il se
replie sur `microtime()`, qui peut suivre une correction de l'heure syst�me.

**La marge est le point important.** `tempsRestant()` r�pond `true` tant que
`ecoule() < limite - marge`. Elle ne dit donc pas � reste-t-il du temps ? � mais
� reste-t-il **au moins une �tape** de temps ? �.

> **?** Une surveillance qui laisse partir un appel sortant � une seconde de la
> limite se fait couper au milieu. C'est exactement la situation o� l'argent est
> d�bit� chez le fournisseur sans que la r�ponse soit enregistr�e chez vous.

**R�glez la marge sur la dur�e de l'�tape la plus longue**, pas sur z�ro. Si un
appel au fournisseur prend jusqu'� huit secondes, la marge est de huit secondes.

**Utilisation** � `Fabrique::surveillance()` la c�ble sur `max_execution_time` :

```php
$limite = (float) ini_get('max_execution_time');
return new SurveillanceTimeout($limite > 0 ? $limite : 30.0, 5.0);
```

---

## 3.6 `AuthentificationDifferee`

**Signature**

```php
final class AuthentificationDifferee implements AuthentificationInterface
{
    public function __construct(callable $construire);   // (): AuthentificationInterface
    public function identifier(): Identite;
}
```

**R�le** � Enveloppe qui retarde la construction du v�ritable authentificateur.

**Ne pas la prendre pour une �l�gance gratuite : elle r�pare un d�faut pr�cis.**

La racine de composition c�ble l'authentificateur dans chaque cas d'usage. Si le
construire exige un d�p�t � donc la base � alors une base injoignable fait
�chouer l'endpoint **avant** qu'il ait pu examiner la requ�te. Une requ�te sans
jeton re�oit � erreur interne � au lieu de � token introuvable � : le diagnostic
rendu au consommateur devient faux, et l'anomalie ne se voit que le jour o� la
base tombe.

Avec cette enveloppe, rien n'est construit tant que `identifier()` n'est pas
appel� � c'est-�-dire tant qu'un cas d'usage n'a pas vraiment besoin de savoir
qui appelle.

**Utilisation** � toujours via `Fabrique::authentificationDifferee()` :

```php
return $this->authentificationDifferee(function () {
    return new Authentificateur(
        $this->requete(),
        $this->jetons(),
        new ResolveurDepot($this->utilisateurs())   // ? touche la base
    );
});
```

**V�rification** � trois contr�les d'auto-test couvrent ce comportement, et la
fumigation HTTP du squelette le montre de bout en bout : base injoignable,
r�ponse `400 token introuvable`.

